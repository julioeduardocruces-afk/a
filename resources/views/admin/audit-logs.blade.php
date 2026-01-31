@extends('layouts.admin')
@section('title', 'Admin - Audit Logs')
@section('admin-content')
<h1 style="margin-bottom:16px;">Audit Logs</h1>

<div class="card" style="overflow-x:auto;">
    <table>
        <thead>
            <tr><th>ID</th><th>Actor</th><th>Accion</th><th>IP</th><th>Metadata</th><th>Fecha</th></tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
            <tr>
                <td>{{ $log->id }}</td>
                <td>{{ $log->actor_type }}:{{ $log->actor_id ?? '-' }}</td>
                <td>{{ $log->action }}</td>
                <td>{{ $log->ip ?? '-' }}</td>
                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;">
                    <small>{{ json_encode($log->metadata_json) }}</small>
                </td>
                <td>{{ $log->created_at?->format('d/m H:i:s') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
{{ $logs->links() }}
@endsection
