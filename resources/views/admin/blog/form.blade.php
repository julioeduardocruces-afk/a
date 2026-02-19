@extends('layouts.admin')
@section('title', isset($post) ? 'Editar Articulo' : 'Nuevo Articulo')
@section('admin-content')
<style>
    .editor-layout { display: grid; grid-template-columns: 1fr 360px; gap: 24px; }
    .editor-main { min-width: 0; }
    .editor-sidebar { }
    .seo-panel { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
    .seo-panel h3 { font-size: 14px; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 8px; }
    .seo-panel h3 svg { width: 18px; height: 18px; }
    .seo-preview { background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
    .seo-preview-title { color: #1a0dab; font-size: 18px; line-height: 1.3; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .seo-preview-url { color: #006621; font-size: 13px; margin-bottom: 4px; }
    .seo-preview-desc { color: #545454; font-size: 13px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .char-count { font-size: 11px; color: #888; text-align: right; margin-top: 4px; }
    .char-count.warning { color: #f59e0b; }
    .char-count.error { color: #dc3545; }
    .focus-keyword-analysis { margin-top: 12px; }
    .analysis-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 13px; }
    .analysis-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .analysis-dot.good { background: #28a745; }
    .analysis-dot.warning { background: #f59e0b; }
    .analysis-dot.bad { background: #dc3545; }
    .image-preview { position: relative; margin-bottom: 12px; }
    .image-preview img { width: 100%; height: 180px; object-fit: cover; border-radius: 8px; }
    .image-preview-remove { position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; font-size: 16px; }
    .status-toggle { display: flex; gap: 8px; }
    .status-toggle label { flex: 1; padding: 12px; text-align: center; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
    .status-toggle input:checked + label { border-color: #0066ff; background: #e8f0fe; }
    .status-toggle input { display: none; }
    @media (max-width: 1024px) {
        .editor-layout { grid-template-columns: 1fr; }
        .editor-sidebar { order: -1; }
    }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <div>
        <a href="{{ route('admin.blog.index') }}" style="color:#666;text-decoration:none;font-size:13px;">&larr; Volver al listado</a>
        <h1 style="margin-top:8px;">{{ isset($post) ? 'Editar Articulo' : 'Nuevo Articulo' }}</h1>
    </div>
    @if(isset($post) && $post->status === 'published')
        <a href="{{ route('blog.show', $post->slug) }}" target="_blank" class="btn btn-secondary">Ver articulo &rarr;</a>
    @endif
</div>

<form method="POST" action="{{ isset($post) ? route('admin.blog.update', $post->id) : route('admin.blog.store') }}" enctype="multipart/form-data" id="blog-form">
    @csrf
    @if(isset($post))
        @method('PUT')
    @endif

    <div class="editor-layout">
        {{-- Main Content --}}
        <div class="editor-main">
            {{-- Title --}}
            <div class="card" style="margin-bottom:20px;">
                <div class="form-group" style="margin-bottom:0;">
                    <input type="text" name="title" id="post-title" value="{{ old('title', $post->title ?? '') }}" required
                           placeholder="Titulo del articulo..."
                           style="font-size:1.5rem;font-weight:600;padding:16px;border:none;width:100%;">
                    @error('title')
                        <div style="color:#dc3545;font-size:12px;margin-top:4px;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Excerpt --}}
            <div class="card" style="margin-bottom:20px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:13px;color:#666;">Extracto (se muestra en las tarjetas del blog)</label>
                    <textarea name="excerpt" rows="2" placeholder="Breve descripcion del articulo..."
                              style="resize:vertical;">{{ old('excerpt', $post->excerpt ?? '') }}</textarea>
                </div>
            </div>

            {{-- Content Editor --}}
            <div class="card" style="margin-bottom:20px;">
                <label style="font-size:13px;color:#666;display:block;margin-bottom:8px;">Contenido</label>
                <textarea name="content" id="editor">{{ old('content', $post->content ?? '') }}</textarea>
                @error('content')
                    <div style="color:#dc3545;font-size:12px;margin-top:4px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="editor-sidebar">
            {{-- Publish Panel --}}
            <div class="seo-panel">
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/></svg>
                    Publicacion
                </h3>

                <div class="status-toggle" style="margin-bottom:16px;">
                    <input type="radio" name="status" id="status-draft" value="draft" {{ old('status', $post->status ?? 'draft') === 'draft' ? 'checked' : '' }}>
                    <label for="status-draft">Borrador</label>
                    <input type="radio" name="status" id="status-published" value="published" {{ old('status', $post->status ?? '') === 'published' ? 'checked' : '' }}>
                    <label for="status-published">Publicado</label>
                </div>

                <div class="form-group">
                    <label>Categoria</label>
                    <select name="category">
                        <option value="">Sin categoria</option>
                        @foreach($categories as $key => $label)
                            <option value="{{ $key }}" {{ old('category', $post->category ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if(isset($post))
                <div class="form-group">
                    <label>URL personalizada</label>
                    <div style="display:flex;align-items:center;gap:4px;">
                        <span style="color:#888;font-size:12px;">/blog/</span>
                        <input type="text" name="custom_slug" value="{{ old('custom_slug', $post->slug) }}" style="flex:1;">
                    </div>
                </div>
                @endif

                <button type="submit" class="btn btn-primary" style="width:100%;">
                    {{ isset($post) ? 'Guardar Cambios' : 'Crear Articulo' }}
                </button>
            </div>

            {{-- Featured Image --}}
            <div class="seo-panel">
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    Imagen Destacada
                </h3>

                <div class="image-preview" id="image-preview" style="{{ isset($post) && $post->featured_image ? '' : 'display:none;' }}">
                    <img src="{{ isset($post) && $post->featured_image ? $post->featured_image_url : '' }}" alt="" id="preview-img">
                    <button type="button" class="image-preview-remove" onclick="removeImage()">&times;</button>
                </div>

                <input type="file" name="featured_image" id="featured-image" accept="image/*" style="display:none;" onchange="previewImage(this)">
                <input type="hidden" name="remove_image" id="remove-image" value="0">

                <button type="button" class="btn btn-secondary" style="width:100%;" onclick="document.getElementById('featured-image').click()">
                    Seleccionar Imagen
                </button>
                <p style="font-size:11px;color:#888;margin-top:8px;">Recomendado: 1200x630px (formato 16:9)</p>
            </div>

            {{-- SEO Panel (like Yoast) --}}
            <div class="seo-panel">
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    SEO
                </h3>

                {{-- Google Preview --}}
                <div class="seo-preview">
                    <div class="seo-preview-title" id="seo-preview-title">{{ $post->seo_title ?? 'Titulo del articulo' }}</div>
                    <div class="seo-preview-url">cvats.cl/blog/<span id="seo-preview-slug">{{ $post->slug ?? 'url-del-articulo' }}</span></div>
                    <div class="seo-preview-desc" id="seo-preview-desc">{{ $post->seo_description ?? 'La descripcion del articulo aparecera aqui...' }}</div>
                </div>

                <div class="form-group">
                    <label>Palabra clave principal</label>
                    <input type="text" name="focus_keyword" id="focus-keyword" value="{{ old('focus_keyword', $post->focus_keyword ?? '') }}"
                           placeholder="Ej: CV ATS optimizado">
                </div>

                {{-- Keyword Analysis --}}
                <div class="focus-keyword-analysis" id="keyword-analysis" style="display:none;">
                    <div class="analysis-item" id="analysis-title">
                        <span class="analysis-dot"></span>
                        <span>Palabra clave en titulo</span>
                    </div>
                    <div class="analysis-item" id="analysis-meta">
                        <span class="analysis-dot"></span>
                        <span>Palabra clave en meta descripcion</span>
                    </div>
                    <div class="analysis-item" id="analysis-content">
                        <span class="analysis-dot"></span>
                        <span>Palabra clave en contenido</span>
                    </div>
                    <div class="analysis-item" id="analysis-slug">
                        <span class="analysis-dot"></span>
                        <span>Palabra clave en URL</span>
                    </div>
                </div>

                <hr style="margin:16px 0;border:none;border-top:1px solid #eee;">

                <div class="form-group">
                    <label>Meta titulo <span style="color:#888;font-weight:normal;">(max 70)</span></label>
                    <input type="text" name="meta_title" id="meta-title" value="{{ old('meta_title', $post->meta_title ?? '') }}"
                           placeholder="Dejar vacio para usar el titulo" maxlength="70">
                    <div class="char-count"><span id="meta-title-count">0</span>/70</div>
                </div>

                <div class="form-group">
                    <label>Meta descripcion <span style="color:#888;font-weight:normal;">(max 160)</span></label>
                    <textarea name="meta_description" id="meta-description" rows="3"
                              placeholder="Descripcion para motores de busqueda..." maxlength="160">{{ old('meta_description', $post->meta_description ?? '') }}</textarea>
                    <div class="char-count"><span id="meta-desc-count">0</span>/160</div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label>Meta keywords <span style="color:#888;font-weight:normal;">(separadas por coma)</span></label>
                    <input type="text" name="meta_keywords" value="{{ old('meta_keywords', $post->meta_keywords ?? '') }}"
                           placeholder="cv, ats, trabajo, empleo">
                </div>
            </div>

            @if(isset($post))
            <div class="seo-panel" style="background:#f8f9fa;">
                <p style="font-size:12px;color:#666;margin:0;">
                    <strong>Creado:</strong> {{ $post->created_at->format('d/m/Y H:i') }}<br>
                    <strong>Vistas:</strong> {{ number_format($post->views_count) }}<br>
                    <strong>Lectura:</strong> ~{{ $post->reading_time }} min
                </p>
            </div>
            @endif
        </div>
    </div>
</form>

{{-- TinyMCE Editor --}}
<script src="https://cdn.tiny.cloud/1/{{ \App\Models\Setting::getValue('tinymce_api_key', 'no-api-key') }}/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#editor',
    height: 500,
    menubar: false,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | bold italic underline strikethrough | ' +
             'alignleft aligncenter alignright alignjustify | ' +
             'bullist numlist outdent indent | link image | removeformat code',
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 16px; line-height: 1.7; }',
    images_upload_url: '{{ route("admin.blog.upload-image") }}',
    automatic_uploads: true,
    images_reuse_filename: true,
    relative_urls: false,
    remove_script_host: false,
    branding: false,
    promotion: false,
    setup: function(editor) {
        editor.on('change keyup', function() {
            editor.save();
            updateSeoAnalysis();
        });
    }
});

// Image preview
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-img').src = e.target.result;
            document.getElementById('image-preview').style.display = 'block';
            document.getElementById('remove-image').value = '0';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImage() {
    document.getElementById('image-preview').style.display = 'none';
    document.getElementById('featured-image').value = '';
    document.getElementById('remove-image').value = '1';
}

// SEO character counters
const metaTitle = document.getElementById('meta-title');
const metaDesc = document.getElementById('meta-description');
const titleCount = document.getElementById('meta-title-count');
const descCount = document.getElementById('meta-desc-count');

function updateCharCount(input, counter, max) {
    const len = input.value.length;
    counter.textContent = len;
    const parent = counter.parentElement;
    parent.classList.remove('warning', 'error');
    if (len > max) parent.classList.add('error');
    else if (len > max * 0.8) parent.classList.add('warning');
}

metaTitle.addEventListener('input', function() {
    updateCharCount(this, titleCount, 70);
    updateSeoPreview();
    updateSeoAnalysis();
});

metaDesc.addEventListener('input', function() {
    updateCharCount(this, descCount, 160);
    updateSeoPreview();
    updateSeoAnalysis();
});

// Initialize counts
updateCharCount(metaTitle, titleCount, 70);
updateCharCount(metaDesc, descCount, 160);

// SEO Preview
const postTitle = document.getElementById('post-title');
const focusKeyword = document.getElementById('focus-keyword');

function updateSeoPreview() {
    const title = metaTitle.value || postTitle.value || 'Titulo del articulo';
    const desc = metaDesc.value || 'La descripcion del articulo aparecera aqui...';
    const slug = postTitle.value ? slugify(postTitle.value) : 'url-del-articulo';

    document.getElementById('seo-preview-title').textContent = title;
    document.getElementById('seo-preview-desc').textContent = desc;
    @if(!isset($post))
    document.getElementById('seo-preview-slug').textContent = slug;
    @endif
}

function slugify(text) {
    return text.toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .substring(0, 60);
}

postTitle.addEventListener('input', function() {
    updateSeoPreview();
    updateSeoAnalysis();
});

// SEO Analysis (like Yoast)
function updateSeoAnalysis() {
    const keyword = focusKeyword.value.toLowerCase().trim();
    const analysisEl = document.getElementById('keyword-analysis');

    if (!keyword) {
        analysisEl.style.display = 'none';
        return;
    }

    analysisEl.style.display = 'block';

    const title = postTitle.value.toLowerCase();
    const meta = metaDesc.value.toLowerCase();
    const content = (tinymce.get('editor')?.getContent({format: 'text'}) || '').toLowerCase();
    const slug = @if(isset($post)) '{{ $post->slug }}' @else slugify(postTitle.value) @endif;

    setAnalysisStatus('analysis-title', title.includes(keyword));
    setAnalysisStatus('analysis-meta', meta.includes(keyword));
    setAnalysisStatus('analysis-content', content.includes(keyword));
    setAnalysisStatus('analysis-slug', slug.includes(keyword.replace(/\s+/g, '-')));
}

function setAnalysisStatus(id, isGood) {
    const dot = document.querySelector('#' + id + ' .analysis-dot');
    dot.classList.remove('good', 'bad');
    dot.classList.add(isGood ? 'good' : 'bad');
}

focusKeyword.addEventListener('input', updateSeoAnalysis);

// Initialize
updateSeoPreview();
setTimeout(updateSeoAnalysis, 1000);
</script>
@endsection
