<?php

namespace App\Http\Middleware;

use App\Models\Resume;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnsResume
{
    /**
     * Ownership protection: ensure the session holds the access_token for this resume.
     * Anonymous users get access via session-stored tokens; admin users bypass via auth.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resumeId = $request->route('resume') ?? $request->route('id');

        if (!$resumeId) {
            abort(400, 'Resume ID requerido.');
        }

        $id = $resumeId instanceof Resume ? $resumeId->id : (int)$resumeId;

        $resume = Resume::find($id);
        if (!$resume) {
            abort(404, 'CV no encontrado.');
        }

        // Admin users can access any resume
        if ($request->user()?->is_admin) {
            $request->attributes->set('resume', $resume);
            return $next($request);
        }

        // Anonymous ownership: check session access_tokens array
        $sessionTokens = $request->session()->get('resume_tokens', []);
        if (!in_array($resume->access_token, $sessionTokens, true)) {
            abort(403, 'No tienes acceso a este recurso.');
        }

        $request->attributes->set('resume', $resume);

        return $next($request);
    }
}
