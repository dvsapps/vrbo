<?php
use Sugin\Router;
use josegonzalez\Dotenv\Loader;
use Httpful\Request;
use GuzzleHttp\Client;


function tick() {
    echo "tick<br>";
}

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: content-type');
    header('Access-Control-Max-Age: 86400');
}
$Dotenv = new Loader(realpath('.env'));
$Dotenv->parse()->putenv(true);
$router = new Router(function ($method, $path, $statusCode, $exception) {
    http_response_code($statusCode);
    include 'views/404.html';
});

$router->get('/', function () {
    echo 'Welcome!';
});
function del() {
    if (!file_exists('downloads')) {
            mkdir('downloads');
    } else {
        $files = glob('downloads/*'); // get all file names
        foreach($files as $file){ // iterate files
            if(is_file($file)) {
                unlink($file);
            }
        }
    }
}
function downloadimg($img, $size) {
    if($size === 1) {
        $img->uri = str_replace('.c10', '', $img->uri);
        $name = $img->uri;
    } else {
        $name = str_replace('.c10', '', $img->uri);
    }
    
    $name = basename($name);
    if($raw = @file_get_contents($img->uri)) {
        $save = 'downloads/' . $name;
        if(file_put_contents($save, $raw)) {
            return $name . ' Saved!<hr>';
        } else {
            return $name . ' Failed!<hr>';
        }
    }
    
    
}
function addToZip() {
    $zip = new ZipArchive;
    $zipname = 'Property-' . rand(1000, 9999) . '.zip';
        if ($zip->open($zipname, ZipArchive::CREATE) === TRUE) {
            $files = glob('downloads/*'); // get all file names
                foreach($files as $file){ // iterate files
                    if(is_file($file)) {
                        $zip->addFile($file);
                    }
                }
            $zip->close();

            return $zipname;
        }
}
$router->get('/get/(.+)/(.+)', function ($url, $size) {
    del();
    $response = Request::get($url)->send();
    $nice = explode('<script>', $response->body);
    $nice = explode('</script>', $nice[3]);
    $nice = trim($nice[0]);
    $nice = strtok($nice, "\n");
    $nice = str_replace('window.__INITIAL_STATE__ = ', '', $nice);
    $nice = str_replace('TREATMENT.', '', $nice);
    $nice = str_replace($nice[-1], '', $nice);
    $ob = json_decode($nice);

    $item = (object) [];
    $item->headline = $ob->listingReducer->headline;
    $item->description = $ob->listingReducer->description;
    $item->img = $ob->listingReducer->images[0]->uri;
    $item->bedrooms = $ob->listingReducer->bedrooms;
    $item->bathrooms = ($ob->listingReducer->bathrooms->full + $ob->listingReducer->bathrooms->half);
    $item->location = $ob->listingReducer->address->city . ', ' . $ob->listingReducer->address->stateProvince . ', ' . $ob->listingReducer->address->postalCode . ', ' . $ob->listingReducer->address->country;
    $item->map = $ob->listingReducer->geoCode;
    file_put_contents('downloads/item.json', json_encode($item));
    $images = $ob->listingReducer->images;
    //each image
    foreach($images as $img) {
        downloadimg($img, $size);
    }

    $zipname = addToZip();
    $srv = Request::get('https://apiv2.gofile.io/getServer')->expectsJson()->send();
    $srv = str_replace('{server}', $srv->body->data->server, 'https://{server}.gofile.io/uploadFile');
    $client = new \GuzzleHttp\Client();

    $response = $client->request('POST', $srv, [
        'multipart' => [
            [
                'name'     => 'file',
                'contents' => fopen(realpath($zipname), 'r'),
                'headers'  => ['Content-Type' => 'application/zip']
            ],
            [
                'name'     => 'email',
                'contents' => 'serge72mt@gmail.com',
            ],
            [
                'name'     => 'ac',
                'contents' => 'adrianminune',
            ],
        ]
    ]);
    $response = json_decode($response->getBody());
    $item->download = $response->data;
    $item->download->url = 'https://gofile.io/d/' . $item->download->code;
    del();
    unlink($zipname);
    header('Content-Type: application/json');
    echo json_encode($item);
});

$router->dispatchGlobal();