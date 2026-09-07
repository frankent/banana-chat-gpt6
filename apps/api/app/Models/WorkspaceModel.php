<?php
namespace App\Models;
use App\Domain\Workspace\WorkspaceContext;
use Illuminate\Database\Eloquent\{Model,Builder};
use Illuminate\Database\Eloquent\Concerns\HasUlids;
abstract class WorkspaceModel extends Model {
    use HasUlids;
    protected $guarded=[];
    protected static function booted():void {
        static::addGlobalScope('workspace',function(Builder $q){
            $id=app(WorkspaceContext::class)->id;
            // Console jobs and Filament must explicitly opt out. Missing HTTP context fails closed.
            if($id) $q->where($q->getModel()->qualifyColumn('workspace_id'),$id);
            elseif(!app()->runningInConsole()) $q->whereRaw('1 = 0');
        });
    }
}
