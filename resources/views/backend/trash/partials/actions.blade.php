<form action="{{ route('backend.trash.restore', ['type' => $type, 'id' => $item->id]) }}" method="POST">
    @csrf
    <x-ui.button variant="ghost" icon="rotate-ccw" :label="__('translation.restore')" type="submit" />
</form>

<form id="delete-form-{{ $type }}-{{ $item->id }}" action="{{ route('backend.trash.forceDelete', ['type' => $type, 'id' => $item->id]) }}" method="POST">
    @csrf
    @method('DELETE')
    <x-ui.button
        variant="danger"
        icon="circle-x"
        :label="__('translation.delete_permanently')"
        data-delete-modal="{{ json_encode(['formId' => 'delete-form-'.$type.'-'.$item->id]) }}"
    />
</form>
