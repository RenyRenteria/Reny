<select name="{{ $name }}">
    <option value="">None</option>
    @foreach ($mediaAssets as $asset)
        @if (in_array($asset->type->value, $types, true))
            <option value="{{ $asset->id }}" @selected((string) $selected === (string) $asset->id)>
                {{ $asset->title ?: $asset->original_filename }}
            </option>
        @endif
    @endforeach
</select>
