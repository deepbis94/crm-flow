<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class NoteService
{
    public function append(Lead $lead, User $agent, string $body, string $type = 'note'): Note
    {
        return Note::query()->create([
            'lead_id' => $lead->id,
            'agent_id' => $agent->id,
            'type' => $type,
            'body' => $body,
            'version' => 1,
            'created_at' => now(),
        ]);
    }

    public function revise(Note $note, User $agent, string $body): Note
    {
        return Note::query()->create([
            'lead_id' => $note->lead_id,
            'agent_id' => $agent->id,
            'type' => $note->type,
            'body' => $body,
            'version' => $note->version + 1,
            'parent_note_id' => $note->id,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(string $term, array $filters = []): Builder
    {
        $leads = Lead::query()->with(['assignedAgent', 'queue', 'source']);

        if ($term !== '') {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $leads->where(function (Builder $q) use ($term) {
                    $q->whereFullText(['name', 'email', 'phone'], $term)
                        ->orWhereHas('notes', fn (Builder $n) => $n->whereFullText(['body'], $term));
                });
            } else {
                $like = '%'.$term.'%';
                $leads->where(function (Builder $q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhereHas('notes', fn (Builder $n) => $n->where('body', 'like', $like));
                });
            }
        }

        foreach (['queue_id', 'state', 'source_id', 'assigned_agent_id', 'sla_status'] as $key) {
            if (! empty($filters[$key])) {
                $leads->where($key, $filters[$key]);
            }
        }

        return $leads->orderByDesc('updated_at');
    }
}
