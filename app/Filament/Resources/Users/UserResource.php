<?php

namespace App\Filament\Resources\Users;

use App\Concerns\HasAvatarFallback;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class UserResource extends Resource
{
    use HasAvatarFallback;

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static \UnitEnum|string|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('username')->required()->maxLength(255)->unique(table: 'users', ignoreRecord: true),
            Forms\Components\TextInput::make('email')->email()->required()->maxLength(255)->unique(table: 'users', ignoreRecord: true),
            Forms\Components\TextInput::make('password')
                ->password()
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->maxLength(255),
            Forms\Components\Textarea::make('bio')->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) User::count();
    }

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.*')
                    && ! request()->is('*/users/banned*'))
                ->sort(static::getNavigationSort())
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->url(static::getNavigationUrl()),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => self::avatarFallbackUrl($record->name))
                    ->width(32)
                    ->height(32),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('username')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('profanity_strikes')
                    ->label('Strikes')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('ban_status')
                    ->label('Ban Status')
                    ->getStateUsing(fn (User $record) => $record->banned_at ? 'Banned' : null)
                    ->badge()
                    ->color('danger')
                    ->placeholder('—')
                    ->alignCenter(),
            ])
            ->filters([
                Filter::make('banned')
                    ->label('Banned users')
                    ->query(fn (Builder $query) => $query->whereNotNull('banned_at')),
                Filter::make('has_strikes')
                    ->label('Has strikes')
                    ->query(fn (Builder $query) => $query->where('profanity_strikes', '>', 0)),
                TernaryFilter::make('is_admin')
                    ->label('Admin status')
                    ->trueLabel('Admins only')
                    ->falseLabel('Non-admins only'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('ban')
                        ->action(function (User $record, Component $livewire) {
                            $record->forceFill(['banned_at' => now()])->save();
                            Notification::make()
                                ->title('User banned')
                                ->body($record->name.' has been banned.')
                                ->danger()
                                ->duration(4000)
                                ->send();
                            $livewire->dispatch('refresh-sidebar');
                        })
                        ->visible(fn (User $record) => $record->banned_at === null)
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon('heroicon-o-no-symbol'),
                    Action::make('unban')
                        ->action(function (User $record, Component $livewire) {
                            $record->forceFill(['banned_at' => null, 'profanity_strikes' => 0])->save();
                            Notification::make()
                                ->title('User unbanned')
                                ->body($record->name.' can now access the platform.')
                                ->success()
                                ->duration(4000)
                                ->send();
                            $livewire->dispatch('refresh-sidebar');
                        })
                        ->visible(fn (User $record) => $record->banned_at !== null)
                        ->color('success')
                        ->icon('heroicon-o-check-circle'),
                    Action::make('reset_strikes')
                        ->label('Reset Strikes')
                        ->action(function (User $record, Component $livewire) {
                            $record->forceFill(['profanity_strikes' => 0])->save();
                            Notification::make()
                                ->title('Strikes reset')
                                ->body($record->name.'\'s profanity strikes have been cleared.')
                                ->success()
                                ->duration(4000)
                                ->send();
                            $livewire->dispatch('refresh-sidebar');
                        })
                        ->requiresConfirmation()
                        ->icon('heroicon-o-arrow-path'),
                    Action::make('toggle_admin')
                        ->label(fn (User $record) => $record->is_admin ? 'Revoke Admin' : 'Make Admin')
                        ->action(function (User $record, Component $livewire) {
                            if ($record->id === auth()->id()) {
                                Notification::make()->title('You cannot revoke your own admin status.')->danger()->send();

                                return;
                            }
                            $record->forceFill(['is_admin' => ! $record->is_admin])->save();
                            Notification::make()
                                ->title($record->is_admin ? 'Admin granted' : 'Admin revoked')
                                ->body($record->name.' admin status has been updated.')
                                ->warning()
                                ->duration(4000)
                                ->send();
                            $livewire->dispatch('refresh-sidebar');
                        })
                        ->disabled(fn (User $record) => $record->id === auth()->id())
                        ->hidden(fn (User $record) => $record->id === auth()->id())
                        ->requiresConfirmation()
                        ->color('warning')
                        ->icon('heroicon-o-shield-check'),
                    EditAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'banned' => Pages\ListBannedUsers::route('/banned'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
