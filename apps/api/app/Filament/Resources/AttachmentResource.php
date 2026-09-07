<?php
namespace App\Filament\Resources;
use App\Models\Attachment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Resource;
class AttachmentResource extends Resource {
 protected static ?string $model=Attachment::class;protected static ?string $navigationIcon='heroicon-o-paper-clip';protected static ?string $navigationLabel='Storage';protected static ?int $navigationSort=5;
 public static function getEloquentQuery():\Illuminate\Database\Eloquent\Builder{return parent::getEloquentQuery()->withoutGlobalScopes()->withTrashed();}
 public static function table(Table $table):Table{return $table->defaultSort('created_at','desc')->columns([Tables\Columns\TextColumn::make('original_name')->searchable(),Tables\Columns\TextColumn::make('kind')->badge(),Tables\Columns\TextColumn::make('status')->badge(),Tables\Columns\TextColumn::make('size_bytes')->formatStateUsing(fn($state)=>number_format($state/1048576,2).' MB')->sortable(),Tables\Columns\TextColumn::make('workspace_id')->searchable(),Tables\Columns\TextColumn::make('created_at')->dateTime()])->filters([Tables\Filters\SelectFilter::make('status')->options(['pending'=>'Pending','uploaded'=>'Uploaded','processing'=>'Processing','ready'=>'Ready','failed'=>'Failed'])]);}
 public static function canCreate():bool{return false;}public static function canEdit($record):bool{return false;}public static function canDelete($record):bool{return false;}
 public static function getPages():array{return ['index'=>AttachmentResource\Pages\ListAttachments::route('/')];}
}
