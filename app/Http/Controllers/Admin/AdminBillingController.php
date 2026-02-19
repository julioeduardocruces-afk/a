<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminBillingController extends Controller
{
    /**
     * List all paid resumes for invoicing.
     */
    public function index(Request $request)
    {
        $query = Resume::with(['payments' => function ($q) {
                $q->where('status', 'paid')->latest();
            }])
            ->whereHas('payments', function ($q) {
                $q->where('status', 'paid');
            })
            ->latest();

        // Filter by invoice status
        if ($request->filled('invoice_status')) {
            $query->where('invoice_status', $request->invoice_status);
        }

        // Search by name, email, or RUT
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('billing_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('billing_rut', 'like', "%{$search}%");
            });
        }

        $resumes = $query->paginate(20);

        return view('admin.billing.index', compact('resumes'));
    }

    /**
     * Show billing details for a resume.
     */
    public function show(int $id)
    {
        $resume = Resume::with(['payments' => function ($q) {
                $q->where('status', 'paid')->latest();
            }])
            ->findOrFail($id);

        $payment = $resume->payments->first();

        return view('admin.billing.show', compact('resume', 'payment'));
    }

    /**
     * Mark invoice as issued.
     */
    public function markIssued(Request $request, int $id)
    {
        $resume = Resume::findOrFail($id);

        $validated = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:50'],
        ]);

        $resume->update([
            'invoice_status' => 'issued',
            'invoice_issued_at' => now(),
            'invoice_number' => $validated['invoice_number'] ?? null,
        ]);

        AuditLog::record('admin.invoice_issued', $request->user()->id, 'admin', [
            'resume_id' => $id,
            'invoice_number' => $validated['invoice_number'] ?? null,
        ], $request->ip());

        return back()->with('success', 'Factura marcada como emitida.');
    }

    /**
     * Upload invoice file.
     */
    public function uploadInvoice(Request $request, int $id)
    {
        $resume = Resume::findOrFail($id);

        $request->validate([
            'invoice_file' => ['required', 'file', 'max:5120', 'mimes:pdf'],
        ]);

        // Delete old invoice if exists
        if ($resume->invoice_file) {
            Storage::disk('local')->delete($resume->invoice_file);
        }

        // Store new invoice
        $filename = 'factura_' . $resume->id . '_' . Str::random(8) . '.pdf';
        $path = $request->file('invoice_file')->storeAs('invoices', $filename, 'local');

        $resume->update([
            'invoice_file' => $path,
            'invoice_status' => 'issued',
            'invoice_issued_at' => $resume->invoice_issued_at ?: now(),
        ]);

        AuditLog::record('admin.invoice_uploaded', $request->user()->id, 'admin', [
            'resume_id' => $id,
            'file' => $filename,
        ], $request->ip());

        return back()->with('success', 'Factura subida correctamente.');
    }

    /**
     * Send invoice email.
     */
    public function sendInvoice(Request $request, int $id)
    {
        $resume = Resume::findOrFail($id);

        if (!$resume->invoice_file || !Storage::disk('local')->exists($resume->invoice_file)) {
            return back()->withErrors(['invoice' => 'No hay factura adjunta para enviar.']);
        }

        $email = $resume->getEmail();
        if (!$email) {
            return back()->withErrors(['email' => 'No hay email de cliente disponible.']);
        }

        $payment = $resume->payments()->where('status', 'paid')->first();
        $amount = $payment ? $payment->amount : config('ats.price_clp', 4990);

        try {
            Mail::send('emails.invoice', [
                'resume' => $resume,
                'payment' => $payment,
            ], function ($message) use ($resume, $email) {
                $message->to($email)
                    ->subject('Factura - CV Optimizer ATS')
                    ->attach(Storage::disk('local')->path($resume->invoice_file), [
                        'as' => 'Factura_CVOptimizerATS.pdf',
                        'mime' => 'application/pdf',
                    ]);
            });

            $resume->update([
                'invoice_status' => 'sent',
                'invoice_sent_at' => now(),
            ]);

            AuditLog::record('admin.invoice_sent', $request->user()->id, 'admin', [
                'resume_id' => $id,
                'email' => $email,
            ], $request->ip());

            return back()->with('success', "Factura enviada a {$email}");

        } catch (\Exception $e) {
            return back()->withErrors(['email' => 'Error al enviar email: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove invoice file.
     */
    public function removeInvoice(Request $request, int $id)
    {
        $resume = Resume::findOrFail($id);

        if ($resume->invoice_file) {
            Storage::disk('local')->delete($resume->invoice_file);
            $resume->update([
                'invoice_file' => null,
            ]);
        }

        return back()->with('success', 'Factura eliminada.');
    }
}
