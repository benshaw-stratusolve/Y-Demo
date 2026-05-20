<?php

namespace App\Filament\Resources\Users;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('username')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required()->maxLength(255),
            Forms\Components\Textarea::make('bio')->maxLength(500)->columnSpanFull(),
            Forms\Components\TextInput::make('location')->maxLength(255),
            Forms\Components\TextInput::make('website')->url()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('username')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('profanity_strikes')->alignCenter(),
                Tables\Columns\IconColumn::make('is_admin')->boolean()->alignCenter(),
                Tables\Columns\TextColumn::make('banned_at')->dateTime()->placeholder('Active')->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('banned')
                    ->label('Banned users')
                    ->query(fn ($query) => $query->whereNotNull('banned_at')),
                Tables\Filters\TernaryFilter::make('is_admin')->label('Admin status'),
            ])
            ->recordActions([
                Action::make('ban')
                    ->action(fn (User $record) => $record->update(['banned_at' => now()]))
                    ->visible(fn (User $record) => $record->banned_at === null)
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-no-symbol'),
                Action::make('unban')
                    ->action(fn (User $record) => $record->update(['banned_at' => null]))
                    ->visible(fn (User $record) => $record->banned_at !== null)
                    ->color('success')
                    ->icon('heroicon-o-check-circle'),
                Action::make('reset_strikes')
                    ->action(fn (User $record) => $record->update(['profanity_strikes' => 0]))
                    ->requiresConfirmation()
                    ->icon('heroicon-o-arrow-path'),
                Action::make('toggle_admin')
                    ->label(fn (User $record) => $record->is_admin ? 'Revoke Admin' : 'Make Admin')
                    ->action(fn (User $record) => $record->update(['is_admin' => ! $record->is_admin]))
                    ->requiresConfirmation()
                    ->color('warning')
                    ->icon('heroicon-o-shield-check'),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
