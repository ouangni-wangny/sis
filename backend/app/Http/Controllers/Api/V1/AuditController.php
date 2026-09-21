<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JournalAuditResource;
use App\Models\JournalAudit;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        $items = JournalAudit::query()
            ->with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('auditable_type'), function ($q) use ($request) {
                $type = $request->string('auditable_type')->toString();
                $q->where(function ($inner) use ($type) {
                    $inner->where('auditable_type', $type)
                        ->orWhere('auditable_type', 'like', '%\\'.$type)
                        ->orWhere('auditable_type', 'like', $type);
                });
            })
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->string('user_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('resume', 'like', $term)
                        ->orWhere('action', 'like', $term)
                        ->orWhere('auditable_type', 'like', $term)
                        ->orWhere('auditable_id', 'like', $term)
                        ->orWhere('ip', 'like', $term)
                        ->orWhereHas('user', function ($user) use ($term) {
                            $user->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            })
            ->latest();

        return JournalAuditResource::collection(ListQuery::paginateOrAll($items, $request, 30));
    }

    public function show(Request $request, JournalAudit $journalAudit): JournalAuditResource
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        return new JournalAuditResource($journalAudit->load('user'));
    }
}
