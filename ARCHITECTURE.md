# ATS CV Optimizer - Architecture Document

## Overview

Micro-service in PHP 8.2+ / Laravel 11 that optimizes CVs for ATS (Applicant Tracking Systems).
Users upload a CV (PDF/DOCX), select target industry/role, the system extracts text,
processes it with AI (OpenAI or Gemini), generates an optimized ATS-friendly CV,
offers a protected preview, and requires payment via Flow (Webpay) to unlock download.

## Architecture Diagram (Text)

```
User Browser
    |
    v
[Landing Pages (SSR/SEO)]  ->  sitemap.xml, robots.txt, Schema.org FAQPage
    |
    v
[Auth: Register/Login/Magic Link]
    |
    v
[Upload CV] -> [Storage: private/uploads/]
    |
    v
[Select Industry + Role]
    |
    v
[Process (Queue Job)]
    |-> TextExtractorService (PDF: smalot/pdfparser, DOCX: PhpWord)
    |-> AiOptimizerService (OpenAI / Gemini, key rotation)
    |-> AtsScoreService (heuristic 0-100)
    |-> Save ResumeVersion
    |
    v
[Preview (Rasterized Image)]
    |-> ResumeRendererService (wkhtmltoimage / GD fallback)
    |-> Dynamic watermark (email + timestamp + resume_id)
    |-> No selectable text served to client
    |
    v
[Payment via Flow (Webpay)]
    |-> PaymentFlowService (create order, HMAC signature)
    |-> Webhook: verify, idempotent, anti-replay
    |
    v
[Generate Final CV (Queue Job)]
    |-> PdfGeneratorService (DOMPDF)
    |-> DocxGeneratorService (PhpWord)
    |-> Storage: private/finals/
    |
    v
[Deliver]
    |-> Email with signed expirable single-use download link
    |-> DownloadController validates token, serves file
```

## Database Schema

```
users              - Standard auth + is_admin + magic_token
resumes            - FK user_id, file metadata, extracted text, structured JSON, target role/industry, status enum, errors
resume_versions    - FK resume_id, version counter, optimized text (md + plain), ATS keywords, score, consistency report
payments           - FK user_id + resume_id, Flow integration fields, idempotency via flow_token/flow_order UNIQUE
api_credentials    - Multi-provider (openai/gemini/flow/smtp), encrypted JSON, usage tracking for rotation
audit_logs         - Actor, action, metadata JSON, IP
metrics_daily      - Aggregated daily stats
download_tokens    - Single-use, expirable, FK resume_id + user_id
```

## State Machine

```
draft -> processing -> preview_ready -> paid -> delivered
              |              |
              v              v
           failed <----------+
              |
              v
         processing (retry)
```

## Endpoints

| Method | Path                            | Auth    | Description                    |
|--------|---------------------------------|---------|--------------------------------|
| GET    | /                               | Public  | Landing page (SEO)             |
| GET    | /como-funciona                  | Public  | How it works                   |
| GET    | /preguntas-frecuentes           | Public  | FAQ with Schema.org            |
| GET    | /sitemap.xml                    | Public  | XML Sitemap                    |
| POST   | /registro                       | Guest   | Register                       |
| POST   | /login                          | Guest   | Login                          |
| POST   | /magic-link                     | Guest   | Send magic link                |
| GET    | /dashboard                      | Auth    | User dashboard                 |
| POST   | /upload                         | Auth    | Upload CV file                 |
| GET    | /resumes/{id}/target-role       | Auth+IDOR | Select target role form     |
| POST   | /resumes/{id}/target-role       | Auth+IDOR | Save target role            |
| POST   | /resumes/{id}/process           | Auth+IDOR | Trigger processing          |
| GET    | /resumes/{id}/status            | Auth+IDOR | Check status (polling)      |
| GET    | /resumes/{id}/preview           | Auth+IDOR | Rasterized preview image    |
| GET    | /resumes/{id}/preview-page      | Auth+IDOR | Preview page with score     |
| POST   | /payments/flow/create           | Auth    | Create Flow payment            |
| POST   | /payments/flow/webhook          | None*   | Flow webhook (signature verify)|
| GET    | /payments/flow/return/{id}      | Auth    | Return from Flow               |
| GET    | /download/{token}               | Token   | Single-use expirable download  |
| GET    | /admin/*                        | Admin   | Admin dashboard/management     |

## Security Checklist

| # | Threat                  | Vector                           | Mitigation                                                  |
|---|-------------------------|----------------------------------|-------------------------------------------------------------|
| 1 | SQL Injection           | User input in queries            | Eloquent ORM / parameterized queries only                   |
| 2 | XSS                     | AI output rendered in browser    | strip_tags on AI output, Blade {{ }} auto-escaping          |
| 3 | CSRF                    | Form submissions                 | Laravel @csrf token on all forms                            |
| 4 | IDOR                    | /resumes/{id} access             | EnsureOwnsResume middleware, ownership check                |
| 5 | SSRF                    | AI/payment API calls             | Hardcoded API endpoints, no user-controlled URLs            |
| 6 | RCE                     | File upload                      | MIME+ext validation, no execution, private storage          |
| 7 | Path Traversal          | File storage/retrieval           | UUID filenames, realpath() validation, storage_path check   |
| 8 | Upload Abuse            | Large/malicious files            | 10MB limit, MIME whitelist (PDF/DOCX only), ext check       |
| 9 | Preview Text Leak       | Copy/select from preview         | Server-side raster (PNG), no text in HTTP response          |
| 10| Payment Bypass          | Skip payment, access final CV    | State machine: download only if status=paid/delivered       |
| 11| Webhook Replay          | Duplicate/fake webhooks          | HMAC verification, idempotent by flow_token UNIQUE          |
| 12| Credential Exposure     | DB access to API keys            | Laravel Crypt::encryptString, hidden from serialization     |
| 13| Brute Force             | Login/upload/payment endpoints   | Rate limiting (throttle middleware)                         |
| 14| AI Hallucination        | Invented experiences/data        | System prompt constraints, consistency report, heuristic    |
| 15| N+1 Queries             | Listings with relations          | Eager loading (with()) on all relation queries              |
| 16| Session Fixation        | Login                            | session()->regenerate() on login                            |
| 17| Download Token Abuse    | Sharing download links           | Single-use tokens, 15min expiry, token invalidation         |
| 18| Admin Escalation        | Non-admin accessing /admin       | EnsureIsAdmin middleware, is_admin check                    |

## AI Prompt Design

The system prompt enforces:
1. No data invention (experiences, dates, companies, numbers)
2. No experience omission (ALL original experiences must appear)
3. ATS format (no tables, columns, emojis, icons)
4. Structured JSON output with consistency report
5. Backend validation: heuristic keyword matching between original and optimized

## Key Design Decisions

1. **DOC format**: Rejected with clear error message. Only PDF/DOCX accepted.
2. **Preview protection**: Server-side rasterization is the primary defense. JS anti-copy is defense-in-depth only.
3. **AI provider rotation**: Round-robin by usage_count, least-used credential selected first.
4. **Payment idempotency**: UNIQUE constraints on flow_token and flow_order prevent double-processing.
5. **Queue fallback**: Laravel queue with Redis; can fallback to sync driver or database driver.
6. **Storage**: Local disk by default, S3-compatible via Laravel filesystem config.
