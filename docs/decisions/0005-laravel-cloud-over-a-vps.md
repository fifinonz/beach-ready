# 5. Laravel Cloud over a VPS

## Context

The original plan deployed with GitHub Actions and Deployer to an Ubuntu VPS: Nginx, PHP-FPM, PostgreSQL, a Supervisor-managed queue worker, a cron entry for the scheduler, and HTTPS, all configured and kept up to date by hand.

Beach Ready's production needs are small but specific: a web app, a scheduler that runs every minute, one queue worker that must stay up, and PostgreSQL. It's also a portfolio project with one maintainer, so time spent on servers is time not spent on the product.

## Decision

Deploy to Laravel Cloud:

- An App cluster with the **Scheduler** toggle on and one `queue:work` background process.
- Laravel Serverless Postgres 17. Lando and CI now run 17 too, so every environment is on the same major version.
- Push-to-deploy from `main`, with migrations as the deploy command.
- CI moves forward from Stage 6 to Stage 2.5, so every stage from here on is tested by GitHub Actions before it's merged.

The setup is in `docs/deployment.md`.

## Why not a VPS

- **Less to run and secure.** No OS patches, PHP upgrades, Nginx config, Supervisor, cron, firewall or certificate renewals. Cloud restarts the queue worker on each deploy and runs the scheduler for us.
- **Managed PostgreSQL with backups.** A self-hosted database means setting up and testing backups, which is easy to skip and painful to need.
- **Zero-downtime deploys built in.** On a VPS that's a Deployer recipe with release directories and symlinks to maintain.
- **Framework support.** Laravel detects Cloud and configures what it needs, such as trusted proxies. Deployment needs no code beyond `onOneServer()` on the scheduled task.

## Trade-off

- **Cost.** A small VPS has a fixed monthly price. On Cloud, the every-minute scheduler keeps the app and the database awake, so neither scales to zero. It costs more than the cheapest VPS for the same traffic.
- **Less control.** No root access, a fixed list of PHP extensions, and an ephemeral filesystem, so uploads would need object storage. None of these matter to Beach Ready today.
- **Lock-in.** The app stays a standard Laravel app with no Cloud-specific code, so moving to a VPS, or to Forge with a VPS, later means writing the deploy recipe, not changing the application.
- **Less to show.** A VPS deploy would demonstrate server administration. That's not what this project is meant to show; the reminder pipeline, time zones and consent are.
