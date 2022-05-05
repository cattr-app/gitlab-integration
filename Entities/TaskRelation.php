<?php

namespace Modules\GitlabIntegration\Entities;

use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modules\GitlabIntegration\Entities\TaskRelation
 *
 * @property int $gitlab_id
 * @property int $task_id
 * @property int $gitlab_issue_iid
 * @property-read Task $task
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRelation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRelation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRelation query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRelation whereGitlabId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRelation whereGitlabIssueIid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRelation whereTaskId($value)
 * @mixin \Eloquent
 */
class TaskRelation extends Model
{
    public $timestamps = false;

    protected $table = 'gitlab_tasks_relations';

    protected $fillable = [
        'gitlab_id',
        'task_id',
        'gitlab_issue_iid',
    ];

    protected $casts = [
        'gitlab_id' => 'int',
        'task_id' => 'int',
        'gitlab_issue_iid' => 'int',
    ];

    protected $primaryKey = 'gitlab_id';

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }
}
