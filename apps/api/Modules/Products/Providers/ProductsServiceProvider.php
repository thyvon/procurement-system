<?php

namespace Modules\Products\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Products\Models\Brand;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductCategory;
use Modules\Products\Models\ProductGroup;
use Modules\Products\Models\Uom;
use Modules\Products\Policies\BrandPolicy;
use Modules\Products\Policies\ProductCategoryPolicy;
use Modules\Products\Policies\ProductGroupPolicy;
use Modules\Products\Policies\ProductPolicy;
use Modules\Products\Policies\UomPolicy;
use Modules\Products\Repositories\BrandRepository;
use Modules\Products\Repositories\BrandRepositoryInterface;
use Modules\Products\Repositories\ProductCategoryRepository;
use Modules\Products\Repositories\ProductCategoryRepositoryInterface;
use Modules\Products\Repositories\ProductGroupRepository;
use Modules\Products\Repositories\ProductGroupRepositoryInterface;
use Modules\Products\Repositories\ProductRepository;
use Modules\Products\Repositories\ProductRepositoryInterface;
use Modules\Products\Repositories\UomRepository;
use Modules\Products\Repositories\UomRepositoryInterface;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ProductsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Products';

    protected string $nameLower = 'products';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(ProductCategoryRepositoryInterface::class, ProductCategoryRepository::class);
        $this->app->bind(BrandRepositoryInterface::class, BrandRepository::class);
        $this->app->bind(ProductGroupRepositoryInterface::class, ProductGroupRepository::class);
        $this->app->bind(UomRepositoryInterface::class, UomRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(ProductGroup::class, ProductGroupPolicy::class);
        Gate::policy(Uom::class, UomPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
    }
}
