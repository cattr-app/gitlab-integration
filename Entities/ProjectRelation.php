<?php

namespace Modules\GitlabIntegration\Entities;

use App\Models\Project;
use App\Scopes\ProjectAccessScope;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modules\GitlabIntegration\Entities\ProjectRelation
 *
 * @property int $gitlab_id
 * @property int $project_id
 * @property-read Project $project
 * @method static Builder|ProjectRelation newModelQuery()
 * @method static Builder|ProjectRelation newQuery()
 * @method static Builder|ProjectRelation query()
 * @method static Builder|ProjectRelation whereGitlabId($value)
 * @method static Builder|ProjectRelation whereProjectId($value)
 * @mixin Eloquent
 */
class ProjectRelation extends Model
{
    // Table that stores the data
    public $timestamps = false;

    // Fields that can be filled in while creating the model
    protected $table = 'gitlab_projects_relations';

    protected $fillable = [
        'gitlab_id',
        'project_id',
    ];

    // Turns off the default created_at and updated_at fields use
    protected $primaryKey = 'gitlab_id';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id')
            ->withoutGlobalScope(ProjectAccessScope::class);
    }
}
