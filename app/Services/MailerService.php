<?php

namespace App\Services;

use App\Models\DownloadToken;
use App\Models\Resume;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailerService
{
    /**
     * Default email content blocks — used when admin hasn't customized them.
     * Each key matches a `settings` table key prefixed with `email_`.
     */
    public const DEFAULTS = [
        'email_subject' => 'Tu CV Optimizado para ATS esta Listo',

        'email_intro' => 'Tu CV ha sido optimizado con exito por nuestro sistema especializado en ATS (Applicant Tracking System). Esto significa que tu CV ahora esta estructurado y redactado para maximizar su visibilidad ante los filtros de seleccion que utilizan las empresas y portales de empleo en Chile y Latinoamerica.',

        'email_section_ats_title' => 'Que significa que tu CV este optimizado para ATS?',

        'email_section_ats_body' => 'Los sistemas ATS son software que las empresas usan para filtrar automaticamente los CVs antes de que un reclutador los vea. Mas del 75% de los CVs son descartados en esta etapa sin ser leidos por una persona.

Tu CV optimizado ahora incluye:
- Palabras clave estrategicas para tu industria y cargo objetivo
- Formato y estructura 100% compatible con lectores automaticos
- Redaccion profesional orientada a logros y resultados medibles
- Seccion de habilidades alineada con lo que buscan los reclutadores en tu rubro',

        'email_section_tips_title' => 'Importante: Como usar tu CV optimizado correctamente',

        'email_section_tips_body' => '1. Actualiza tu perfil en los portales de empleo: No basta con adjuntar el CV como archivo. Es fundamental que copies la informacion de tu CV optimizado directamente en los campos del perfil de plataformas como LinkedIn, Trabajando.com, Indeed, CompuTrabajo, Laborum y ChileTrabajos. Los filtros de estas plataformas leen los datos del perfil para recomendar candidatos a los reclutadores, no solo el archivo adjunto.

2. Completa TODA la informacion del perfil: Muchos postulantes solo suben el archivo PDF pero dejan el perfil vacio o incompleto. Esto hace que el sistema no te detecte como candidato potencial, aunque tu CV sea excelente. La diferencia entre ser contactado o pasar desapercibido esta en tener el perfil 100% completo con la informacion de tu CV optimizado.

3. Usa las mismas palabras clave: Cuando postules a una oferta, verifica que las palabras clave del cargo aparezcan tanto en tu CV como en tu perfil del portal. Esto aumenta significativamente tu ranking en las busquedas de los reclutadores.

4. Postula dentro de las primeras 48 horas: Las ofertas reciben la mayoria de postulaciones en los primeros dias. Postular temprano aumenta tu visibilidad ante los reclutadores.

5. Personaliza segun la oferta: Si bien este CV esta optimizado para tu area, puedes ajustar el resumen profesional para cada postulacion especifica destacando la experiencia mas relevante para ese cargo.',

        'email_footer' => 'Te deseamos mucho exito en tu busqueda laboral. Recuerda que un CV optimizado es solo el primer paso: la clave esta en como lo utilizas en cada plataforma y en mantener tus perfiles actualizados.',
    ];

    /**
     * Get an email content block: checks admin settings first, falls back to default.
     */
    public static function getEmailContent(string $key): string
    {
        return Setting::getValue($key, self::DEFAULTS[$key] ?? '');
    }

    /**
     * Send the final CV download link to the customer.
     * Works for both anonymous (customer_email) and registered users.
     */
    public function sendFinalCvEmail(Resume $resume): void
    {
        $email = $resume->getEmail();
        if (!$email) {
            throw new \RuntimeException('No email available for resume #' . $resume->id);
        }

        $displayName = $resume->getDisplayName();
        $ttlMinutes = (int) config('ats.download_token_ttl', 1440);
        $maxDownloads = (int) config('ats.max_downloads', 3);

        // Single token for both formats, allows up to max_downloads uses
        $token = DownloadToken::generate($resume->id, $resume->user_id, $ttlMinutes, $maxDownloads);

        $downloadPdfUrl = route('download.token', ['token' => $token->token]);
        $downloadDocxUrl = route('download.token', ['token' => $token->token, 'format' => 'docx']);

        // Load editable content blocks (cached via Setting::getValue)
        $subject = self::getEmailContent('email_subject');

        $viewData = [
            'userName' => $displayName,
            'downloadPdfUrl' => $downloadPdfUrl,
            'downloadDocxUrl' => $downloadDocxUrl,
            'expiresAt' => $token->expires_at->format('d/m/Y H:i'),
            'maxDownloads' => $maxDownloads,
            'emailSubject' => $subject,
            'emailIntro' => self::getEmailContent('email_intro'),
            'sectionAtsTitle' => self::getEmailContent('email_section_ats_title'),
            'sectionAtsBody' => self::getEmailContent('email_section_ats_body'),
            'sectionTipsTitle' => self::getEmailContent('email_section_tips_title'),
            'sectionTipsBody' => self::getEmailContent('email_section_tips_body'),
            'emailFooter' => self::getEmailContent('email_footer'),
        ];

        try {
            Mail::send(
                'emails.cv-ready',
                $viewData,
                function ($message) use ($email, $displayName, $resume, $subject) {
                    $message->to($email, $displayName)
                        ->subject($subject . ' - #' . $resume->id);
                }
            );

            Log::info('CV email sent', [
                'email' => $email,
                'resume_id' => $resume->id,
                'token_id' => $token->id,
                'max_downloads' => $maxDownloads,
            ]);
        } catch (\Exception $e) {
            $token->update(['used' => true]);

            Log::error('Failed to send CV email, token invalidated', [
                'email' => $email,
                'resume_id' => $resume->id,
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
