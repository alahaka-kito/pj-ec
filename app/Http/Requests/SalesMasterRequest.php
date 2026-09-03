<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'variations.*.shipping_size' => ['nullable', 'integer', 'in:60,80,100,120,140,160,180,200'],
            'variations.*.cool_delivery_service' => ['nullable', 'boolean'],
            'variations.*.time_delivery_service' => ['nullable', 'boolean'],
            'variations.*.products' => ['required', 'array', 'min:1'],
            'variations.*.products.*.product_management_code' => ['required', 'string', 'max:50', 'exists:product_master,product_management_code'],
            'variations.*.products.*.quantity' => ['required', 'integer', 'min:1'],
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
            'variations.*.shipping_size' => '配送サイズ',
            'variations.*.products.*.product_management_code' => '対応商品管理コード',
            'variations.*.products.*.quantity' => '使用個数',
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
            'variations.*.products.*.product_management_code.exists' => '選択された:attributeは登録されていません。',
        ];
    }
}