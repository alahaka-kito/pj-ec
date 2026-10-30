<?php

namespace App\Http\Requests;

use App\Models\ProductMaster;
use Illuminate\Foundation\Http\FormRequest;

class SalesMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $variations = $this->input('variations', []);

        foreach ($variations as &$variation) {
            if (!isset($variation['products']) || !is_array($variation['products'])) {
                continue;
            }

            foreach ($variation['products'] as &$product) {
                if (!is_array($product)) {
                    continue;
                }

                if (!empty($product['product_master_seq']) || empty($product['product_management_code'])) {
                    continue;
                }

                $code = trim((string) $product['product_management_code']);
                $productMaster = ProductMaster::where('product_management_code_tiktok', $code)->first(['seq'])
                    ?? ProductMaster::where('product_management_code_amazon', $code)->first(['seq']);

                if ($productMaster) {
                    $product['product_master_seq'] = $productMaster->seq;
                }
            }
            unset($product);
        }
        unset($variation);

        $this->merge(['variations' => $variations]);
    }

    public function rules(): array
    {
        return [
            'product_name' => ['required', 'string', 'max:255'],
            'amazon_url' => ['nullable', 'url', 'max:2048'],
            'tiktok_url' => ['nullable', 'url', 'max:2048'],
            'drive_path' => ['nullable', 'string', 'max:512'],
            'variations' => ['required', 'array', 'min:1'],
            'variations.*.variation_name' => ['required', 'string', 'max:255'],
            'variations.*.tikTok_sku_id' => ['nullable', 'string', 'max:255'],
            'variations.*.amazon_sku_id' => ['nullable', 'string', 'max:255'],
            'variations.*.variation_stock_linking' => ['nullable', 'boolean'],
            'variations.*.shipping_size' => ['nullable', 'integer', 'in:60,80,100,120,140,160,180,200'],
            'variations.*.cool_delivery_service' => ['nullable', 'boolean'],
            'variations.*.time_delivery_service' => ['nullable', 'boolean'],
            'variations.*.products' => ['required', 'array', 'min:1'],
            'variations.*.products.*.product_master_seq' => ['required', 'integer', 'exists:product_master,seq'],
            'variations.*.products.*.quantity' => ['required', 'integer', 'min:1'],
            'variations.*.products.*.variation_stock' => ['required', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'product_name' => '販売名',
            'amazon_url' => 'Amazon販売ページURL',
            'tiktok_url' => 'TikTok販売ページURL',
            'drive_path' => 'ドライブパス',
            'variations' => 'バリエーション',
            'variations.*.variation_name' => 'バリエーション名',
            'variations.*.tikTok_sku_id' => 'SKU ID(TikTok)',
            'variations.*.amazon_sku_id' => 'SKU ID(Amazon)',
            'variations.*.shipping_size' => '配送サイズ',
            'variations.*.products.*.product_master_seq' => '対象商品',
            'variations.*.products.*.quantity' => '使用個数',
            'variations.*.products.*.variation_stock' => 'バリエーション在庫数',
        ];
    }

    /**
     * バリデーションエラーメッセージの定義
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variations.*.products.*.product_master_seq.exists' => '選択された:attributeは登録されていません。',
        ];
    }
}
