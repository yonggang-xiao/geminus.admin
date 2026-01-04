<?php

declare(strict_types=1);

if (! function_exists('uploaded_file_url')) {
    /**
     * Get the full URL for an uploaded file given its path.
     *
     * @param string $filePath The relative path of the uploaded file.
     *
     * @return string The full URL to access the uploaded file.
     */
    function uploaded_file_url(string $type, string $filename): string
    {
        // Assuming files are stored in 'uploads' directory under the base URL
        return base_url('admin/files/' . $type . '/' . basename($filename));
    }
}