# Deployment

Beach Ready runs on [Laravel Cloud](https://cloud.laravel.com). Every push to `main` deploys automatically, and GitHub Actions runs Pint and the tests on every push and pull request. Why Laravel Cloud and not a VPS: [decision 0005](decisions/0005-laravel-cloud-over-a-vps.md).

## What runs where

| Piece | Where it runs | Notes |
|---|---|---|
| Web app and API | Laravel Cloud **App cluster** | One small replica is plenty to start. |
| Scheduler | App cluster, **Scheduler** toggle | Cloud runs `schedule:run` every minute. The only task is `reminders:dispatch-due`. |
| Queue worker | App cluster, **background process** | One `queue:work` process on the `database` connection. It sends reminder emails. |
| Database | **Laravel Serverless Postgres 17** | Holds everything, including sessions, cache, the job queue and failed jobs. |
| Mail | Log driver until Stage 3, then MailerSend | No email can go out before Stage 3 anyway: nobody can confirm an address yet. |
| CI | GitHub Actions, `.github/workflows/ci.yml` | Pint, plus the Unit, Feature and Concurrency suites against PostgreSQL 17. |

The queue, sessions and cache all use the database, so the only resource to attach is Postgres. A Valkey cache or Cloud's managed queues can be added later if the load ever calls for them. Managed queues would also need the `aws/aws-sdk-php` package and would change `QUEUE_CONNECTION` to `cloud`.

## First-time setup in the Laravel Cloud dashboard

Do these in order. Laravel Cloud's own docs for each step are linked.

### 1. Create the application

1. **New application**, then connect GitHub and choose `fifinonz/beach-ready`.
2. **Branch:** `main`. Keep push-to-deploy on, so merging a pull request deploys it.
3. **Region:** the one closest to your users. The database must be in the same region, so decide once.

### 2. General settings

- **PHP version:** 8.4.

### 3. Deployments ([docs](https://cloud.laravel.com/docs/environments#build-and-deploy-commands))

**Build commands**, replacing the defaults:

```sh
composer install --no-dev
npm ci --audit false
npm run build
php artisan optimize
```

**Deploy commands:**

```sh
php artisan migrate --force
```

Don't add `queue:restart`, `optimize:clear` or `storage:link`. Cloud restarts queue workers on every deploy, and the other two do more harm than good there.

### 4. Database ([docs](https://cloud.laravel.com/docs/resources/databases/postgres))

On the environment's infrastructure canvas, click **Add database** and create a new cluster:

- **Type:** Laravel Serverless Postgres, version **17**, the same as Lando and CI.
- **Cluster name:** `beach-ready`
- **Region:** the same as the application.
- **Configuration:** the smallest preset.
- **Database name:** `beach_ready`

Cloud injects `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`, so don't set them yourself.

**Don't turn on connection pooling.** That means don't set `DB_POOLING` and don't switch `DB_HOST` to the `-pooler` host. The direct connection keeps two things working the same way they do locally and in CI:

- **Time zone:** `config/database.php` sets each connection's session time zone to UTC (decision 0002). A pooler in transaction mode doesn't keep session settings between transactions.
- **Row locks:** sending a reminder holds a row lock for the length of a transaction (decision 0004).

At this size, the direct connection's limits are nowhere near a problem.

### 5. App cluster: scheduler and queue worker ([scheduler docs](https://cloud.laravel.com/docs/scheduled-tasks), [worker docs](https://cloud.laravel.com/docs/workers))

Click the **App cluster** on the canvas, then:

1. Turn on the **Scheduler** toggle.
2. Under **Background processes**, click **New background process** and choose the queue worker configuration:
   - **Connection:** `database`
   - **Queue:** `default`
   - **Processes:** 1
3. Leave **scale to zero** off for this cluster. The queue worker must not be stopped part-way through a send, and the every-minute scheduler would wake it constantly anyway.

### 6. Environment variables ([docs](https://cloud.laravel.com/docs/environments#environment-variables))

Add these in the environment's settings. Anything not listed (database credentials, logging) is injected by Cloud.

| Variable | Value | Why |
|---|---|---|
| `APP_NAME` | `Beach Ready` | Shown in emails. |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | Never show stack traces to users. |
| `APP_URL` | `https://<your-app>.laravel.cloud` for now, the custom domain later | Used for links in emails, and by Sanctum to recognise the SPA as a first-party client. |
| `APP_KEY` | see below | Encrypts sessions and cookies. |
| `SESSION_DRIVER` | `database` | Sessions survive deploys and work across replicas. |
| `SESSION_SECURE_COOKIE` | `true` | The session cookie is only sent over HTTPS. |
| `CACHE_STORE` | `database` | Also used by the scheduler's `withoutOverlapping` and `onOneServer` locks. |
| `QUEUE_CONNECTION` | `database` | The reminder jobs (decision 0004). |
| `MAIL_MAILER` | `log` | Until Stage 3 adds MailerSend. |
| `MAIL_FROM_ADDRESS` | an address on your domain | |
| `MAIL_FROM_NAME` | `Beach Ready` | |

**`APP_KEY`:** check whether Cloud has already set one. If not, open the environment's **Commands** tab, run `php artisan key:generate --show`, and paste the output (it starts with `base64:`) as `APP_KEY`. Keep it stable: changing it logs everyone out.

Redeploy after changing variables. They only take effect on the next deploy.

### 7. Deploy and check

Click **Deploy**. When it finishes, run these in the **Commands** tab:

| Command | Expect |
|---|---|
| `php artisan migrate:status` | Every migration `Ran`. |
| `php artisan schedule:list` | `reminders:dispatch-due` every minute. |
| `php artisan tinker --execute="echo DB::selectOne('show timezone')->TimeZone;"` | `UTC` |
| `php artisan queue:failed` | No failed jobs. |

Then open `https://<your-app>.laravel.cloud/up`, which should return 200.

### Later: custom domain

Add the domain in the environment's **Domains** settings ([docs](https://cloud.laravel.com/docs/domains)). Then set `APP_URL` to it and redeploy, so email links and Sanctum use the new address.

## Running costs to keep in mind

The scheduler runs every minute, and `withoutOverlapping` queries the database cache each time. Together they keep the App cluster and the database awake around the clock, so neither will scale to zero. That's the price of reminders that arrive within a minute. If the bill matters more than that, the scheduler could run every five minutes, at the cost of reminders arriving up to five minutes late.

## GitHub

- **CI** runs on every push and pull request. Laravel Cloud deploys `main` regardless of CI, so a pull request is only merged once CI is green.
- **Branch protection** is now possible. In the repository's **Settings → Rules**, protect `main`: require a pull request, require the **Code style** and **Tests** checks to pass, and block force pushes. See `docs/workflow.md`.
