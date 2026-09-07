<?php
namespace App\Filament\Resources;
use App\Models\Room;
use Filament\{Forms,Tables};
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Support\Audit;
class RoomResource extends Resource {
 protected static ?string $model=Room::class;protected static ?string $navigationIcon='heroicon-o-chat-bubble-left-right';protected static ?int $navigationSort=4;
 public static function getEloquentQuery():\Illuminate\Database\Eloquent\Builder{return parent::getEloquentQuery()->withoutGlobalScopes()->withTrashed();}
 public static function form(Form $form):Form{return $form->schema([Forms\Components\TextInput::make('name')->maxLength(100),Forms\Components\Textarea::make('description')->maxLength(500),Forms\Components\TextInput::make('workspace_id')->disabled(),Forms\Components\TextInput::make('member_count')->disabled()]);}
 public static function table(Table $table):Table{return $table->columns([Tables\Columns\TextColumn::make('name')->placeholder('Direct message')->searchable(),Tables\Columns\TextColumn::make('type')->badge(),Tables\Columns\TextColumn::make('workspace_id')->searchable(),Tables\Columns\TextColumn::make('member_count')->sortable(),Tables\Columns\TextColumn::make('last_message_at')->dateTime()->sortable(),Tables\Columns\TextColumn::make('deleted_at')->dateTime()])->actions([Tables\Actions\EditAction::make(),Tables\Actions\Action::make('restore')->visible(fn(Room $record)=>$record->trashed())->requiresConfirmation()->action(function(Room $record){$record->restore();$record->update(['purge_after'=>null]);Audit::write('room.restored',$record->id);}),Tables\Actions\DeleteAction::make()->visible(fn(Room $record)=>!$record->trashed())]);}
 public static function getPages():array{return ['index'=>RoomResource\Pages\ManageRooms::route('/')];}
}
