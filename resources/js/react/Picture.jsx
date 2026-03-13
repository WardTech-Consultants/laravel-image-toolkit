import React from 'react';

/**
 * Responsive <picture> component for images optimized by wardtech/laravel-image-toolkit.
 *
 * @param {string}   src       - Image path relative to public (e.g. "images/hero.jpg")
 * @param {string}   alt       - Alt text (required)
 * @param {number}   [size]    - Fixed size variant (e.g. 300). Omit for responsive srcset.
 * @param {string}   [sizes]   - Sizes attribute for responsive mode (default: "100vw")
 * @param {number[]} [widths]  - Available widths (default: [150, 300, 500, 1000])
 * @param {string}   [baseUrl] - Base URL prefix (default: "" — assumes paths relative to domain root)
 * @param {object}   [rest]    - Any extra props are spread onto the <img> element
 */
export default function Picture({
    src,
    alt,
    size = null,
    sizes = '100vw',
    widths = [150, 300, 500, 1000],
    baseUrl = '',
    ...rest
}) {
    const clean = src.replace(/\.\.\//g, '').replace(/^\//, '');
    const lastDot = clean.lastIndexOf('.');
    const pathWithoutExt = lastDot !== -1 ? clean.substring(0, lastDot) : clean;
    const extension = lastDot !== -1 ? clean.substring(lastDot + 1) : 'jpg';

    const url = (path) => `${baseUrl}/${path}`;

    if (size) {
        const webpSrc = url(`${pathWithoutExt}-${size}.webp`);
        const imgSrc = url(`${pathWithoutExt}-${size}.${extension}`);

        return (
            <picture>
                <source type="image/webp" srcSet={webpSrc} />
                <img src={imgSrc} alt={alt} {...rest} />
            </picture>
        );
    }

    const webpSrcset = widths
        .map((w) => `${url(`${pathWithoutExt}-${w}.webp`)} ${w}w`)
        .concat(`${url(`${pathWithoutExt}.webp`)} 9999w`)
        .join(', ');

    const originalSrcset = widths
        .map((w) => `${url(`${pathWithoutExt}-${w}.${extension}`)} ${w}w`)
        .concat(`${url(clean)} 9999w`)
        .join(', ');

    return (
        <picture>
            <source type="image/webp" srcSet={webpSrcset} sizes={sizes} />
            <img
                src={url(clean)}
                srcSet={originalSrcset}
                sizes={sizes}
                alt={alt}
                {...rest}
            />
        </picture>
    );
}
