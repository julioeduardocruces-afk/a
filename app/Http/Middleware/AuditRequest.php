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

        // Log sensitive actions (non-blocking: deferred to after response is sent)
        $sensitiveRoutes = [
            'upload', 'process', 'download', 'payment',
        ];

        $path = $request->path();
        foreach ($sensitiveRoutes as $keyword) {
            if (str_contains($path, $keyword)) {
                // Only log state-changing requests (POST/PUT/DELETE) to reduce write volume
                // GET requests for preview/status are read-only and don't need audit trail
                if (!in_array($request->method(), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
                    break;
                }

                // Capture values before response is sent (request object may not be available later)
                $logData = [
                    'action' => "request.{$request->method()}.{$path}",
                    'actorId' => $request->user()?->id,
                    'actorType' => $request->user() ? 'user' : 'anonymous',
                    'metadata' => [
                        'status' => $response->getStatusCode(),
                        'user_agent' => substr($request->userAgent() ?? '', 0, 200),
                    ],
                    'ip' => $request->ip(),
                ];

                // Defer DB write to after the response is sent to the browser
                app()->terminating(function () use ($logData) {
                    try {
                        AuditLog::record(
                            action: $logData['action'],
                            actorId: $logData['actorId'],
                            actorType: $logData['actorType'],
                            metadata: $logData['metadata'],
                            ip: $logData['ip'],
                        );
                    } catch (\Throwable $e) {
                        // Never let audit logging crash the response
                    }
                });
                break;
            }
        }

        return $response;
    }
}
