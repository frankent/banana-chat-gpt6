<?php
namespace App\Filament\Resources;
use App\Models\Workspace;
use Filament\{Forms,Tables};
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
class WorkspaceResource extends Resource {
 protected static ?string $model=Workspace::class;protected static ?string $navigationIcon='heroicon-o-building-office-2';protected static ?int $navigationSort=2;
 public static function form(Form $form):Form{return $form->schema([Forms\Components\TextInput::make('name')->required()->maxLength(100),Forms\Components\TextInput::make('slug')->required()->minLength(3)->maxLength(40)->regex('/^[a-z0-9-]+$/')->unique(ignoreRecord:true),Forms\Components\Select::make('status')->options(['active'=>'Active','archived'=>'Archived'])->default('active')->required(),Forms\Components\TextInput::make('message_retention_days')->numeric()->minValue(1)->helperText('Empty means keep forever.'),Forms\Components\TextInput::make('attachment_retention_days')->numeric()->minValue(1)]);}
 public static function table(Table $t):Table{return $t->columns([Tables\Columns\TextColumn::make('name')->searchable(),Tables\Columns\TextColumn::make('slug')->searchable(),Tables\Columns\TextColumn::make('status')->badge(),Tables\Columns\TextColumn::make('memberships_count')->counts('memberships')->label('Members')])->actions([Tables\Actions\EditAction::make()]);}
 public static function getPages():array{return ['index'=>WorkspaceResource\Pages\ManageWorkspaces::route('/')];}public static function canDelete($record):bool{return false;}
}
