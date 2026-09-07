<?php
namespace App\Filament\Resources;
use App\Models\AuditLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Resource;
class AuditLogResource extends Resource {
 protected static ?string $model=AuditLog::class;protected static ?string $navigationIcon='heroicon-o-clipboard-document-list';
 public static function table(Table $t):Table{return $t->defaultSort('created_at','desc')->columns([Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),Tables\Columns\TextColumn::make('action')->searchable(),Tables\Columns\TextColumn::make('actor_id')->searchable()->copyable(),Tables\Columns\TextColumn::make('workspace_id')->searchable(),Tables\Columns\TextColumn::make('target_id')->searchable(),Tables\Columns\TextColumn::make('ip')]);}
 public static function canCreate():bool{return false;}public static function canEdit($r):bool{return false;}public static function canDelete($r):bool{return false;}
 public static function getPages():array{return ['index'=>AuditLogResource\Pages\ListAuditLogs::route('/')];}
}
