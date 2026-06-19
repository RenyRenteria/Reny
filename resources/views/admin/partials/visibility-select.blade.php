<select name="{{ $name }}">
    @if ($nullable ?? false)
        <option value="">None</option>
    @endif
    @foreach ($visibilityAudiences as $audience)
        <option value="{{ $audience->value }}" @selected((string) $selected === (string) $audience->value)>
            {{ $audience->value }}
        </option>
    @endforeach
</select>
