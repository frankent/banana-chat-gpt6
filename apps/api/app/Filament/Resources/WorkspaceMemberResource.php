<?php
namespace App\Filament\Resources;
use App\Models\WorkspaceMember;
use Filament\{Forms,Tables};
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Validation\Rule;
class WorkspaceMemberResource extends Resource {
 protected static ?string $model=WorkspaceMember::class;protected static ?string $navigationIcon='heroicon-o-user-plus';protected static ?string $navigationLabel='Workspace memberships';protected static ?int $navigationSort=3;
 public static function form(Form $form):Form{return $form->schema([Forms\Components\Select::make('workspace_id')->relationship('workspace','name')->searchable()->preload()->required()->disabledOn('edit'),Forms\Components\Select::make('user_id')->relationship('user','display_name')->searchable()->preload()->required()->disabledOn('edit')->rules(fn(Forms\Get $get,$record)=>[Rule::unique('workspace_members','user_id')->where('workspace_id',$get('workspace_id'))->ignore($record?->id)]),Forms\Components\Select::make('role')->options(['owner'=>'Owner','admin'=>'Admin','member'=>'Member'])->default('member')->required(),Forms\Components\Select::make('status')->options(['active'=>'Active','removed'=>'Removed'])->default('active')->required(),Forms\Components\Hidden::make('joined_at')->default(now()->toISOString())]);}
 public static function table(Table $t):Table{return $t->columns([Tables\Columns\TextColumn::make('workspace.name')->searchable(),Tables\Columns\TextColumn::make('user.display_name')->searchable(),Tables\Columns\TextColumn::make('role')->badge(),Tables\Columns\TextColumn::make('status')->badge()])->actions([Tables\Actions\EditAction::make()]);}
 public static function getPages():array{return ['index'=>WorkspaceMemberResource\Pages\ManageMembers::route('/')];}public static function canDelete($record):bool{return false;}
 public static function getEloquentQuery():\Illuminate\Database\Eloquent\Builder{return parent::getEloquentQuery()->withoutGlobalScopes();}
}
