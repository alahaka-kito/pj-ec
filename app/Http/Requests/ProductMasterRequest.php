<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class ProductMasterRequest
 *
 * 商品マスタの登録・更新処理に関するバリデーションリクエストクラス
 *
 * @package App\Http\Requests
 */
class ProductMasterRequest extends FormRequest
{
    /**
     * ユーザーがこのリクエストを行う権限があるかどうかを判定
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * リクエストに適用するバリデーションルールを取得
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // ルーティングパラメータ等から現在のレコードの seq を取得（編集時のみ値が入る）
        $productSeq = $this->route('seq') ?? $this->route('product_master') ?? $this->seq;

        return [
            'image'                   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'management_code'         => 'required|string|exists:supplier_master,management_code',
            'product_management_code' => [
                'required',
                'string',
                'max:50',
                // product_master テーブルの product_management_code カラムで重複チェック
                // 更新時は自分自身の seq を除外
                Rule::unique('product_master', 'product_management_code')->ignore($productSeq, 'seq'),
            ],
            'supplier_product_name'   => 'required|string|max:255',
            'buying_price'            => 'required|integer|min:0',
            'stock'                   => 'nullable|integer|min:0',
            'size'                    => 'required|integer|in:60,80,100,120,140,160,180,200',
            'cool_delivery_service'   => 'nullable|boolean',
            'time_delivery_service'   => 'nullable|boolean',
            'drive_path'              => 'nullable|url|max:512',
            'selling_places'          => 'nullable|array',
        ];
    }

    /**
     * エラーメッセージを日本語にカスタマイズ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // 必須入力（required）のメッセージ定義
            'management_code.required'         => ':attributeは必須項目です。',
            'product_management_code.required' => ':attributeは必須項目です。',
            'supplier_product_name.required'   => ':attributeは必須項目です。',
            'buying_price.required'            => ':attributeは必須項目です。',
            'size.required'                    => ':attributeは必須項目です。',

            // 重複チェック（unique）のメッセージ定義
            'product_management_code.unique'   => '指定された:attributeはすでに登録されています。',

            // その他の形式チェック用の日本語メッセージ
            'image.image'                      => '商品画像には画像ファイルを指定してください。',
            'image.mimes'                      => '商品画像は jpeg, png, jpg, gif 形式のファイルを指定してください。',
            'image.max'                        => '商品画像は2MB以下のファイルを指定してください。',
            'management_code.exists'           => '選択された仕入先管理コードは仕入先マスタに存在しません。',
            'product_management_code.max'      => '商品管理コードは50文字以内で入力してください。',
            'supplier_product_name.max'        => '仕入先商品名は255文字以内で入力してください。',
            'buying_price.integer'             => '仕入れ値は整数で入力してください。',
            'buying_price.min'                 => '仕入れ値は0以上の数値を入力してください。',
            'stock.integer'                    => '在庫数は整数で入力してください。',
            'stock.min'                        => '在庫数は0以上の数値を入力してください。',
            'size.in'                          => '商品サイズは選択肢の中から指定してください。',
            'drive_path.url'                   => 'ドライブパスには有効なURLを入力してください。',
            'drive_path.max'                   => 'ドライブパスは512文字以内で入力してください。',
        ];
    }

    /**
     * バリデーションエラーメッセージに使用される属性のカスタム表示名を取得
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'image'                   => '商品画像',
            'management_code'         => '仕入先管理コード',
            'product_management_code' => '商品管理コード',
            'supplier_product_name'   => '仕入先商品名',
            'buying_price'            => '仕入れ値',
            'stock'                   => '在庫数',
            'size'                    => '商品サイズ',
            'cool_delivery_service'   => 'クール宅急便',
            'time_delivery_service'   => '宅急便タイムサービス',
            'drive_path'              => 'ドライブパス',
            'selling_places'          => '販売先',
        ];
    }
}