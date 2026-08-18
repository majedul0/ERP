<?php

namespace App\Http\Controllers\Products;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\UpdateProduct;
use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\SaveProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * List the company's products.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('company/products/index', [
            'products' => $team->products()
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product) => $this->present($product))
                ->all(),
        ]);
    }

    /**
     * Show the registration form.
     */
    public function create(): Response
    {
        return Inertia::render('company/products/create');
    }

    /**
     * Register a new product.
     */
    public function store(SaveProductRequest $request, CreateProduct $createProduct): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $createProduct->handle(
            $team,
            $request->safe()->except('photo'),
            $request->file('photo'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product added.')]);

        return to_route('products.index', ['current_team' => $team->slug]);
    }

    /**
     * Show the edit form.
     *
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function edit(Request $request, string $current_team, Product $product): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($product->team_id === $team->id, 404);

        return Inertia::render('company/products/edit', [
            'product' => $this->present($product),
        ]);
    }

    /**
     * Save the changes.
     */
    public function update(
        SaveProductRequest $request,
        string $current_team,
        Product $product,
        UpdateProduct $updateProduct,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($product->team_id === $team->id, 404);

        $updateProduct->handle(
            $product,
            $request->safe()->except('photo'),
            $request->file('photo'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('products.index', ['current_team' => $team->slug]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'cartonSize' => $product->carton_size,
            'distributorPrice' => $product->distributor_price,
            'tradePrice' => $product->trade_price,
            'mrp' => $product->mrp,
            'stockQuantity' => $product->stock_quantity,
            'photoUrl' => $product->photoUrl(),
        ];
    }
}
