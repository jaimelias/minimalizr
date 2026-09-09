<?php

if (!defined('WPINC')) exit;



if(!function_exists('cloudflare_html_to_pdf')) {

    // valid formats: letter, legal, tabloid, ledger, a0, a1, a2, a3, a4, a5, a6
    // headerTemplate and footerTemplate are separate mini HTML documents — they don't inherit CSS from your main invoice HTML.
    // printBackground renders bg colors/images — needed for logo headers, shaded tables
    // margin units: mm, px, in, cm


    function wrap_html_for_cloudflare ($value, $lang = 'en') {
        return <<<HTML
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: Inter, sans-serif; font-size: 10pt;}
            table, tr, td { vertical-align: top; }
            td { padding: 12pt 8pt; line-height: 1.25; }
        </style>
    </head>
    <body>
    $value
    </body>
    </html>
    HTML;
    }

    function cloudflare_html_to_pdf(
        $html, 
        $filename, 
        $pdfOptions = [
            'format'             => 'a4',
            'printBackground'    => true,          
            'margin'             => [
                'top'    => '20mm',
                'bottom' => '20mm',
                'left'   => '15mm',
                'right'  => '15mm',
            ],
            'displayHeaderFooter' => true,
            'headerTemplate'     => '', 
            'footerTemplate'     => '',
        ]
    ) {
        $pdf_api_token      = trim((string) get_option('dy_cloudflare_pdf_api_token'));
        $account_id = trim((string) get_option('dy_cloudflare_account_id'));

        if($pdf_api_token === '' || $account_id === '') {
            return null;
        }

        $upload_dir    = wp_upload_dir();
        $temp_path     = $upload_dir['basedir'];
        $temp_filename = '/temp_' . uniqid() . '.pdf';
        $pdf_path      = $temp_path . $temp_filename;





        $response = wp_remote_post(
            "https://api.cloudflare.com/client/v4/accounts/{$account_id}/browser-rendering/pdf",
            array(
                'timeout' => 30, // rendering takes longer than a typical WP request
                'headers' => array(
                    'Authorization' => 'Bearer ' . $pdf_api_token,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'html' => wrap_html_for_cloudflare($html),
                    'pdfOptions' => $pdfOptions,
                )),
            )
        );

        if ( is_wp_error( $response ) ) {

            write_log(
                [
                    'message' => 'Cloudflare PDF request could not be completed.',
                    'error'   => $response->get_error_message()
                ],
                false,
                false,
                'ERROR'
            );

            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if($code !== 200)
        {
            write_log(
                [
                    'message'            => 'Cloudflare PDF request failed.',
                    'http_status'        => $code,
                    'response_excerpt'   => substr($body, 0, 4096),
                    'response_truncated' => strlen($body) > 4096
                ],
                false,
                false,
                'ERROR'
            );

            return null;
        }

        file_put_contents( $pdf_path, $body );

        return array( 'filename' => $filename, 'pathname' => $pdf_path );
    }

}



?>