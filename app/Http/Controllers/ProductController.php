<?php

namespace App\Http\Controllers;

use App\AddProduct;
use App\Product;
use Brick\StructuredData\Reader\JsonLdReader;
use Brick\StructuredData\Reader\RdfaLiteReader;
use Brick\StructuredData\Reader\ReaderChain;
use DOMDocument;
use http\Exception\RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Brick\StructuredData\Reader\MicrodataReader;
use Brick\StructuredData\HTMLReader;
use Brick\StructuredData\Item;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function index()
    {
        $products = Auth::user()->products()->paginate(10);

        return view('add_product.list', [
            'products' => $products,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function create()
    {
        return view('add_product.add');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'link' => 'required|min:20|max:65536|url'
        ]);

        /**
         * $ebay = strpos(strval($request), 'ebay');
         * $etsy = strpos(strval($request), 'etsy');
         * $alibaba = strpos(strval($request), 'alibaba');
         * $pttavm = strpos(strval($request), 'pttavm');
         * $n11 = strpos(strval($request), 'n11');
         *
         * if ($ebay !== false){
         * $productDetails = $this->fetchWithLDJsonEbay($request->get('link'));
         * }
         * elseif ($etsy !== false){
         * $productDetails = $this->fetchWithLDJsonEtsy($request->get('link'));
         * }
         * elseif ($alibaba !== false){
         * $productDetails = $this->fetchWithLDJsonAlibaba($request->get('link'));
         * }
         * elseif ($pttavm !== false){
         * $productDetails = $this->fetchWithLDJsonPttAvm($request->get('link'));
         * }
         * elseif ($n11 !== false){
         * $productDetails = $this->fetchWithLDJsonN11($request->get('link'));
         * }
         */
        $iriProperties = [
            'http://schema.org/image'
        ];
        // Let's read Microdata here;
        // You could also use RdfaLiteReader, JsonLdReader,
        // or even use all of them by chaining them in a ReaderChain
        $microdataReader = new ReaderChain(
            new MicrodataReader(),
            new RdfaLiteReader(),
            new JsonLdReader($iriProperties)
        );

        // Wrap into HTMLReader to be able to read HTML strings or files directly,
        // i.e. without manually converting them to DOMDocument instances first
        $htmlReader = new HTMLReader($microdataReader);
        // Replace this URL with that of a website you know is using Microdata
        $url = $request->get('link');
        $html = file_get_contents($url);
        // Read the document and return the top-level items found
        // Note: the URL is only required to resolve relative URLs; no attempt will be made to connect to it
        $items = $htmlReader->read($html, $url);
        // Loop through the top-level items
        foreach ($items as $item) {
            echo implode(',', $item->getTypes()), PHP_EOL;
            dd($item->getProperties());
            foreach ($item->getProperties() as $name => $values) {;
                foreach ($values as $value) {
                    if ($value instanceof Item) {
                        // We're only displaying the class name in this example; you would typically
                        // recurse through nested Items to get the information you need
                        $value = '(' . implode(', ', $value->getTypes()) . ')';
                    }

                    // If $value is not an Item, then it's a plain string

                    echo "  - $name: $value", PHP_EOL;
                }
            }
        }

        $productDetails = $this->fetchWithLDJsonPttAvm($request->get('link'));
        $values = [
            'user_id' => Auth::id(),
            'name' => 'Test',
            'image' => 'Test',
            'link' => 'Test',
            'price' => '100',
            'json' => $productDetails['json'],
        ];

        $product = new Product($values);
        $product->save();

        return redirect()->route('products.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Product $product
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $users_product = Product::where('user_id', '=', Auth::id())->where('id', '=', $id)->delete();

        return redirect()->route('products.index', [
        ]);
    }

    private function fetchWithLDJsonEbay(string $link)
    {
        $response = Http::get($link);
        $content = $response->getBody();
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($content);
        $finder = new \DOMXPath($doc);


        $productValueScrape = $finder->query("//script[@type='application/ld+json']")->item(0);
        $productValues = json_decode($productValueScrape->nodeValue);
        $productName = $productValues->mainEntity->offers->itemOffered[0]->name;
        $productImage = $productValues->mainEntity->offers->itemOffered[0]->image;
        $productPrice = $productValues->mainEntity->offers->itemOffered[0]->offers[0]->price;
        return [
            'productName' => $productName,
            'productPrice' => $productPrice,
            'productImage' => $productImage,
        ];
    }

    private function fetchWithLDJsonEtsy(string $link)
    {
        $response = Http::get($link);
        $content = $response->getBody();
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($content);
        $finder = new \DOMXPath($doc);


        $productValueScrape = $finder->query("//script[@type='application/ld+json']")->item(0);
        $productValues = json_decode($productValueScrape->nodeValue);

        $productName = $productValues->name;
        $productImage = $productValues->image;
        $productPrice = $productValues->offers->lowPrice;

        return [
            'productName' => $productName,
            'productPrice' => $productPrice,
            'productImage' => $productImage,
        ];
    }

    private function fetchWithLDJsonAlibaba(string $link)
    {
        $response = Http::get($link);
        $content = $response->getBody();
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($content);
        $finder = new \DOMXPath($doc);


        $productValueScrape = $finder->query("//script[@type='application/ld+json']")->item(0);
        $productValues = json_decode($productValueScrape->nodeValue);
        $productName = $productValues[0]->name;
        $productImage = $productValues[0]->image;
        $productPrice = $productValues[0]->offers->price;

        return [
            'productName' => $productName,
            'productPrice' => $productPrice,
            'productImage' => $productImage,
        ];
    }


    private function fetchWithLDJsonPttAvm(string $link)
    {
        $response = Http::get($link);
        $content = $response->getBody();
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($content);
        $finder = new \DOMXPath($doc);


        $productValueScrape = $finder->query("//script[@type='application/ld+json']")->item(0);
        $productValues = $productValueScrape->nodeValue;
        $productValuesDecoded = json_decode($productValueScrape->nodeValue);

        /**
         * $productName = $productValuesDecoded->name;
         * $productImage = $productValuesDecoded->image;
         * $productPrice = $productValuesDecoded->offers->price;
         */
        return [
            'json' => $productValues,
        ];
    }

    private function fetchWithLDJsonN11(string $link)
    {
        $response = Http::get($link);
        $content = $response->getBody();
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($content);
        $finder = new \DOMXPath($doc);


        $productValueScrape = $finder->query("//script[@type='application/ld+json']")->item(1);
        $productValues = json_decode($productValueScrape->nodeValue);
        $productName = $productValues->name;
        $productImage = $productValues->image;
        $productPrice = $productValues->offers->price;

        return [
            'productName' => $productName,
            'productPrice' => $productPrice,
            'productImage' => $productImage,
        ];
    }
}
