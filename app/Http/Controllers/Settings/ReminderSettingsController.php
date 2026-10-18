<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ReminderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateReminderSettingsRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Which reminder types a user wants. Emails still only go out once the
 * address is confirmed and fasting reminder emails are on.
 */
class ReminderSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->settingsOf($request->user());
    }

    public function update(UpdateReminderSettingsRequest $request): JsonResponse
    {
        $user = $request->user();

        foreach ($request->validated() as $type => $on) {
            $user->{ReminderType::from($type)->settingColumn()} = $on;
        }

        $user->save();

        return $this->settingsOf($user);
    }

    private function settingsOf(User $user): JsonResponse
    {
        return response()->json(['data' => collect(ReminderType::cases())
            ->mapWithKeys(fn (ReminderType $type) => [$type->value => $user->{$type->settingColumn()}])
            ->all()]);
    }
}
