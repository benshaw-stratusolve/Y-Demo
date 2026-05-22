<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Users\UserResource;
use App\Models\Post;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopPostsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return 'Top Posts';
    }

    protected static ?int $sort = 4;

    protected static bool $isDiscovered = true;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => Post::withCount('likes')
                    ->whereNull('repost_of_id')
                    ->whereNull('parent_post_id')
                    ->whereNotNull('body')
                    ->orderByDesc('likes_count')
                    ->limit(10)
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\ImageColumn::make('user.avatar_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => UserResource::avatarFallbackUrl($record->user->name))
                    ->width(32)
                    ->height(32),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Author'),
                Tables\Columns\TextColumn::make('body')
                    ->label('Post')
                    ->limit(80),
                Tables\Columns\TextColumn::make('likes_count')
                    ->label('Likes')
                    ->badge()
                    ->color('danger')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('replies_count')
                    ->counts('replies')
                    ->label('Replies')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Posted')
                    ->date()
                    ->alignEnd(),
            ]);
    }
}
