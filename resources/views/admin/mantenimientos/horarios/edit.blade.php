<form method="POST" action="{{ route('admin.mantenimientos.horarios.update', $horario->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    @include('admin.mantenimientos.horarios._form')
</form>
