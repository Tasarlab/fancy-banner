@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <ul class="col-md-8">
                @if(count($products) > 0)
                    @foreach($products as $product)

                        <div class="card mb-3">
                            <div class="row no-gutters">
                                <p>{{ $product->json }}</p>
                            </div>

                            <div class="card-footer text-muted">
                                <form action="{{ route('products.destroy', ['product' => $product->id])}}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger float-right">
                                        Remove
                                    </button>
                                </form>
                                <a class="btn btn-primary" onclick="window.open('{{ $product->link }}', '_blank')" role="button">Go Product</a>


                            </div>
                        </div>
                    @endforeach
                    <div class="mt-2">
                        {{ $products->links() }}
                    </div>
                @else
                    <div class="alert-info alert mt-3 text-center">You don't have any products yet!</div>
                @endif
            </ul>
        </div>
    </div>
    </div>
@endsection
