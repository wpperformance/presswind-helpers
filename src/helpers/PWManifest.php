<?php

namespace PressWind\Helpers;

class PWManifest
{
    /**
     * @throws \Exception
     */
    public static function get($path): array
    {
        $self = new self();
        // add trailing slash if not exist
        $path = str_ends_with($path, '/') ? $path : $path.'/';
        $manifest = $self->get_file($path);

        return $self->order_manifest($manifest);
    }

    /**
     * get manifest file generated by vite
     *
     * @param  string  $path - path to manifest file from root theme default:
     * dist/manifest.json
     *
     * @throws \Exception
     */
    public function get_file(string $path = ''): object
    {
        try {
            $strJsonFileContents = file_get_contents(get_template_directory().'/'.$path.'dist/manifest.json');
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return json_decode(str_replace(
            '\u0000',
            '',
            $strJsonFileContents
        ));
    }

    /**
     * get token name from file name
     *
     * @param  string  $key - key from manifest file
     * @return string
     */
    private function get_token_name($key)
    {
        // model $key assets/main-legacy-fe2da1bc.js
        $k = explode('-', $key);
        $token = $key;
        // ex: $k[1] | $k[2] = fe2da1bc.js
        if (array_key_exists(1, $k)) {
            // take key 1 or 2
            $t = array_key_exists(2, $k) ? explode('.', $k[2]) : explode('.', $k[1]);
            // ex: $kt[0] = fe2da1bc
            if (array_key_exists(0, $t)) {
                $token = $t[0];
            }
        }

        return $token;
    }

    public function order_manifest($manifest)
    {
        // remove no entry
        $cleaned = $this->keep_entries($manifest);

        // ordered
        $ordered = $this->move_legacy_and_polyfill($cleaned);
        $orderedWithToken = [];
        // add token
        foreach ($ordered['ordered'] as $key => $value) {
            if (! $value) {
                continue;
            }
            $orderedWithToken[$this->get_token_name($value->file)] = $value;
        }

        return $orderedWithToken;
    }

    /**
     * move polyfill and legacy at the end of array
     */
    public function move_legacy_and_polyfill($manifest): array
    {

        $legacy = null;
        $polyfill = null;
        $cleaned = [];
        foreach ($manifest as $key => $value) {
            // polyfill
            if (strpos($value->src, 'polyfills') > 0 && strpos($value->src, 'legacy') > 0) {
                $polyfill = $value;
                // legacy
            } elseif (strpos($value->src, 'polyfills') === false && strpos($value->src, 'legacy') > 0) {
                $legacy = $value;
            } else {
                $cleaned[] = $value;
            }
        }

        return [
            'legacy' => $legacy,
            'polyfill' => $polyfill,
            'cleaned' => $cleaned,
            // polyfill before legacy
            'ordered' => array_merge($cleaned, [$polyfill, $legacy]),
        ];
    }

    /**
     * remove value in manifest without isEntry key
     */
    private function keep_entries($manifest)
    {
        $clean = [];
        foreach ($manifest as $key => $value) {
            // keep css file entry
            if (strpos($key, '.css') > 0) {
                $clean[] = $value;
            }
            if (property_exists($value, 'isEntry') === true) {
                $clean[] = $value;
            }
        }

        return $clean;
    }
}
