<?php

namespace App\Filament\Resources\Posts;

use App\Concerns\HasAvatarFallback;
use App\Models\Post;
use App\Notifications\PostDeletedNotification;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class PostResource extends Resource
{
    use HasAvatarFallback;

    protected static ?string $model = Post::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static \UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereNull('repost_of_id'))
            ->columns([
                Tables\Columns\ImageColumn::make('user.avatar_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => self::avatarFallbackUrl($record->user->name))
                    ->width(32)
                    ->height(32),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Author')
                    ->searchable(),
                Tables\Columns\TextColumn::make('body')
                    ->limit(80)
                    ->searchable(),
                Tables\Columns\TextColumn::make('likes_count')
                    ->counts('likes')
                    ->label('Likes')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('has_image')
                    ->label('Has image')
                    ->query(fn (Builder $query) => $query->whereNotNull('image')),
                Filter::make('has_replies')
                    ->label('Has replies')
                    ->query(fn (Builder $query) => $query->has('replies')),
                Filter::make('has_likes')
                    ->label('Has likes')
                    ->query(fn (Builder $query) => $query->has('likes')),
                Filter::make('popular')
                    ->label('Popular (10+ likes)')
                    ->query(fn (Builder $query) => $query->has('likes', '>=', 10)),
                Tables\Filters\SelectFilter::make('period')
                    ->label('Posted')
                    ->options([
                        'today' => 'Today',
                        'week' => 'This week',
                        'month' => 'This month',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'today' => $query->whereDate('created_at', today()),
                        'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                        'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                        default => $query,
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_image')
                        ->label('View Image')
                        ->icon('heroicon-o-photo')
                        ->color('info')
                        ->visible(fn (Post $record) => $record->image !== null)
                        ->modalHeading('Post Image')
                        ->modalContent(fn (Post $record) => new HtmlString(
                            '<div class="flex justify-center p-4"><img src="'.e(Storage::disk('public')->url($record->image)).'" alt="Post image" style="max-height:70vh;max-width:100%;border-radius:0.5rem;" /></div>'
                        ))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),
                    DeleteAction::make()
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for deletion')
                                ->placeholder('Explain why this post is being removed...')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (Post $record, array $data) {
                            $excerpt = $record->body ?? '';
                            if ($record->user) {
                                $record->user->notify(new PostDeletedNotification($excerpt, $data['reason'] ?? null));
                            }
                            $record->delete();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Post::whereNull('repost_of_id')->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
        ];
    }
}
