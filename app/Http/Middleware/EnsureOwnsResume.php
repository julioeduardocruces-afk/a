<?php

namespace App\Http\Middleware;

use App\Models\Resume;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnsResume
{
    /**
     * IDOR protection: ensure the authenticated user owns the resume.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resumeId = $request->route('resume') ?? $request->route('id');

        if ($resumeId) {
            $id = $resumeId instanceof Resume ? $resumeId->id : (int)$resumeId;

            $resume = Resume::find($id);
            if (!$resume || !$resume->belongsToUser($request->user()->id)) {
                abort(403, 'No tienes acceso a este recurso.');
            }

            // Share resume with request for downstream use
            $request->attributes->set('resume', $resume);
        }

        return $next($request);
    }
}
