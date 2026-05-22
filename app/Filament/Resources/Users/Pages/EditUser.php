<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ban')
                ->action(function () {
                    $this->record->forceFill(['banned_at' => now()])->save();
                    Notification::make()
                        ->title('User banned')
                        ->body($this->record->name.' has been banned.')
                        ->danger()
                        ->duration(4000)
                        ->send();
                    $this->dispatch('refresh-sidebar');
                })
                ->visible(fn () => $this->record->banned_at === null)
                ->requiresConfirmation()
                ->color('danger')
                ->icon('heroicon-o-no-symbol'),

            Action::make('unban')
                ->action(function () {
                    $this->record->forceFill(['banned_at' => null, 'profanity_strikes' => 0])->save();
                    Notification::make()
                        ->title('User unbanned')
                        ->body($this->record->name.' can now access the platform.')
                        ->success()
                        ->duration(4000)
                        ->send();
                    $this->dispatch('refresh-sidebar');
                })
                ->visible(fn () => $this->record->banned_at !== null)
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Action::make('reset_strikes')
                ->label('Reset Strikes')
                ->action(function () {
                    $this->record->update(['profanity_strikes' => 0]);
                    Notification::make()
                        ->title('Strikes reset')
                        ->body($this->record->name.'\'s profanity strikes have been cleared.')
                        ->success()
                        ->duration(4000)
                        ->send();
                    $this->dispatch('refresh-sidebar');
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-arrow-path'),

            Action::make('toggle_admin')
                ->label(fn () => $this->record->is_admin ? 'Revoke Admin' : 'Make Admin')
                ->action(function () {
                    $this->record->forceFill(['is_admin' => ! $this->record->is_admin])->save();
                    Notification::make()
                        ->title($this->record->is_admin ? 'Admin granted' : 'Admin revoked')
                        ->body($this->record->name.' admin status has been updated.')
                        ->warning()
                        ->duration(4000)
                        ->send();
                    $this->dispatch('refresh-sidebar');
                })
                ->requiresConfirmation()
                ->color('warning')
                ->icon('heroicon-o-shield-check'),

            DeleteAction::make(),
        ];
    }
}
