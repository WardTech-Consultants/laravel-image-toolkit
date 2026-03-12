@props([
    'src',
    'alt',
    'size' => null,
    'sizes' => null,
])

@php
    $info = pathinfo($src);
    $dir = $info['dirname'] !== '.' ? $info['dirname'] . '/' : '';
    $filename = $info['filename'];
    $extension = $info['extension'] ?? 'jpg';
    $configSizes = config('image-toolkit.sizes', [150, 300, 500, 1000]);

    $basePath = public_path();

    // Also check storage path
    $resolveFile = function (string $relativePath) use ($basePath) {
        $publicFile = $basePath . '/' . $relativePath;
        if (file_exists($publicFile)) {
            return $relativePath;
        }

        $storageFile = storage_path('app/public/' . $relativePath);
        if (file_exists($storageFile)) {
            return 'storage/' . $relativePath;
        }

        return null;
    };

    if ($size) {
        // Fixed size mode
        $webpVariant = $resolveFile($dir . $filename . "-{$size}.webp");
        $originalVariant = $resolveFile($dir . $filename . "-{$size}.{$extension}");

        // Fall back to original if variants don't exist
        $imgSrc = $originalVariant ? asset($originalVariant) : asset($src);
        $webpSrc = $webpVariant ? asset($webpVariant) : null;
    } else {
        // Responsive srcset mode
        $webpSrcset = [];
        $originalSrcset = [];

        foreach ($configSizes as $w) {
            $webpFile = $resolveFile($dir . $filename . "-{$w}.webp");
            if ($webpFile) {
                $webpSrcset[] = asset($webpFile) . " {$w}w";
            }

            $origFile = $resolveFile($dir . $filename . "-{$w}.{$extension}");
            if ($origFile) {
                $originalSrcset[] = asset($origFile) . " {$w}w";
            }
        }

        // Add the original full-size image to srcsets
        $originalFull = $resolveFile($src);
        if ($originalFull) {
            $origAsset = asset($originalFull);
        } else {
            $origAsset = asset($src);
        }

        $webpFull = $resolveFile($dir . $filename . '.webp');
        if ($webpFull) {
            $webpFullAsset = asset($webpFull);
        }

        $imgSrc = $origAsset;
        $sizesAttr = $sizes ?? '100vw';
    }
@endphp

@if ($size)
    <picture>
        @if ($webpSrc)
            <source type="image/webp" srcset="{{ $webpSrc }}">
        @endif
        <img src="{{ $imgSrc }}" alt="{{ $alt }}" {{ $attributes }}>
    </picture>
@else
    <picture>
        @if (! empty($webpSrcset) || isset($webpFullAsset))
            @php
                $allWebp = $webpSrcset;
                if (isset($webpFullAsset)) {
                    $allWebp[] = $webpFullAsset . ' 9999w';
                }
            @endphp
            <source type="image/webp"
                    srcset="{{ implode(', ', $allWebp) }}"
                    sizes="{{ $sizesAttr }}">
        @endif
        @if (! empty($originalSrcset))
            @php
                $allOriginal = $originalSrcset;
                $allOriginal[] = $imgSrc . ' 9999w';
            @endphp
            <img src="{{ $imgSrc }}"
                 srcset="{{ implode(', ', $allOriginal) }}"
                 sizes="{{ $sizesAttr }}"
                 alt="{{ $alt }}"
                 {{ $attributes }}>
        @else
            <img src="{{ $imgSrc }}" alt="{{ $alt }}" {{ $attributes }}>
        @endif
    </picture>
@endif
