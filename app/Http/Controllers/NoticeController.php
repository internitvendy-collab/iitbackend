<?php

namespace App\Http\Controllers;

use App\Http\Requests\LegacyEndpointRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Public notices shown on the login screen, before a user authenticates.
 */
class NoticeController extends Controller
{
    private const TYPES = ['Info', 'Warning', 'Event', 'Maintenance'];

    public function index(LegacyEndpointRequest $request): JsonResponse
    {
        $limit = min(50, max(1, $request->integer('limit', 20)));

        $query = $this->publishedQuery();

        $count = (clone $query)->count();

        $notices = $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get([
                'notice_id', 'title', 'message', 'type', 'is_pinned',
                'audience', 'published_at', 'expires_at',
            ])
            ->map(static fn ($row): array => [
                'notice_id' => (int) $row->notice_id,
                'title' => $row->title,
                'message' => $row->message,
                'type' => $row->type,
                'is_pinned' => (bool) $row->is_pinned,
                'audience' => $row->audience,
                'published_at' => $row->published_at,
                'expires_at' => $row->expires_at,
            ])
            ->values();

        return ApiResponse::success([
            'count' => $notices->count(),
            'total_count' => $count,
            'notices' => $notices,
        ]);
    }

    public function store(LegacyEndpointRequest $request): JsonResponse
    {
        $id = DB::table('Notices')->insertGetId([
            'title' => trim($request->string('title')->toString()),
            'message' => trim($request->string('message')->toString()),
            'type' => $this->normalizeType($request->string('type')->toString()),
            'is_published' => $request->boolean('is_published', true),
            'is_pinned' => $request->boolean('is_pinned'),
            'audience' => $request->string('audience')->toString() ?: 'All',
            'published_at' => now(),
            'expires_at' => $request->date('expires_at'),
            'created_by' => $request->user()?->email,
        ]);

        return ApiResponse::success(['notice_id' => $id], 201, 'Notice published successfully.');
    }

    public function update(LegacyEndpointRequest $request): JsonResponse
    {
        $id = $request->integer('notice_id');
        $notice = DB::table('Notices')->where('notice_id', $id)->first();
        if (!$notice) {
            return ApiResponse::error('Notice not found.', 404);
        }

        $updates = [];
        foreach (['title', 'message', 'audience'] as $field) {
            if ($request->filled($field)) {
                $updates[$field] = trim($request->string($field)->toString());
            }
        }
        if ($request->filled('type')) {
            $updates['type'] = $this->normalizeType($request->string('type')->toString());
        }
        if ($request->exists('is_published')) {
            $updates['is_published'] = $request->boolean('is_published');
        }
        if ($request->exists('is_pinned')) {
            $updates['is_pinned'] = $request->boolean('is_pinned');
        }
        if ($request->exists('expires_at')) {
            $updates['expires_at'] = $request->date('expires_at');
        }

        if ($updates === []) {
            return ApiResponse::error('No fields to update.', 400);
        }

        DB::table('Notices')->where('notice_id', $id)->update($updates);

        return ApiResponse::success([], 200, 'Notice updated successfully.');
    }

    public function destroy(LegacyEndpointRequest $request): JsonResponse
    {
        $deleted = DB::table('Notices')
            ->where('notice_id', $request->integer('notice_id'))
            ->delete();

        return $deleted
            ? ApiResponse::success([], 200, 'Notice deleted successfully.')
            : ApiResponse::error('Notice not found.', 404);
    }

    /**
     * Staff view: includes unpublished notices.
     */
    public function manage(LegacyEndpointRequest $request): JsonResponse
    {
        $notices = DB::table('Notices')
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit(100)
            ->get([
                'notice_id', 'title', 'message', 'type', 'is_published',
                'is_pinned', 'audience', 'published_at', 'expires_at', 'created_by',
            ]);

        return ApiResponse::success([
            'count' => $notices->count(),
            'notices' => $notices,
        ]);
    }

    private function publishedQuery()
    {
        return DB::table('Notices')
            ->where('is_published', true)
            ->where('published_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    private function normalizeType(string $type): string
    {
        foreach (self::TYPES as $allowed) {
            if (strcasecmp($allowed, $type) === 0) {
                return $allowed;
            }
        }

        return 'Info';
    }
}
