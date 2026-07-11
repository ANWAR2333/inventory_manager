{{--
    Ancienne version : un SVG en une seule couleur (currentColor), qui s'adaptait
    automatiquement au mode clair/sombre.
    Nouvelle version : une image PNG avec ses propres couleurs fixes. Les classes
    comme "fill-current" ou "text-white dark:text-black" passées via $attributes
    n'auront plus d'effet sur la couleur (elles sont sans danger, juste inutiles).
    Les classes de taille (size-5, size-8, size-9, h-7...) continuent de fonctionner.
--}}
<img src="{{ asset('logo-icon.png') }}" alt="{{ config('app.name', 'Logo') }}" {{ $attributes }}>