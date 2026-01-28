<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log sensitive actions
        $sensitiveRoutes = [
            'upload', 'process', 'preview', 'download', 'payment',
        ];

        $path = $request->path();
        foreach ($sensitiveRoutes as $keyword) {
            if (str_contains($path, $keyword)) {
                AuditLog::record(
                    action: "request.{$request->method()}.{$path}",
                    actorId: $request->user()?->id,
                    actorType: $request->user() ? 'user' : 'anonymous',
                    metadata: [
                        'status' => $response->getStatusCode(),
                        'user_agent' => substr($request->userAgent() ?? '', 0, 200),
                    ],
                    ip: $request->ip(),
                );
                break;
            }
        }

        return $response;
    }
}
