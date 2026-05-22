<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBannedUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'Banned Users';
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->whereNotNull('banned_at');
    }
}
