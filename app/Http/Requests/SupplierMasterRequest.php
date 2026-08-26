<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierMasterRequest extends FormRequest
{
    public function authorize()
    {
        return true; // 認証はミドルウェアで制御するためtrue
    }

    public function rules()
    {
        return [
            'management_code'               => 'required|string|max:50',                // 管理コード：必須
            'hatsu_ninushi_code'            => 'nullable|string|max:50',                // 発荷主コード
            'company_name'                  => 'required|string|max:255',               // 会社名：必須
            'manager'                       => 'nullable|string|max:100',               // 担当者
            'manager_telephone_number'      => 'nullable|regex:/^[0-9-]+$/|max:20',     // 担当者連絡先（数字・ハイフン）
            'selling_places'                => 'nullable|array',                        // 販売先：配列型を許可(Amazon、Tiktok)
            'post_code'                     => 'required|regex:/^[0-9]{3}-[0-9]{4}$/',  // 郵便番号（7桁で-あり）：必須
            'main_address'                  => 'required|string|max:255',               // 住所：必須
            'building_name'                 => 'nullable|string|max:255',               // 建物名（部屋番号）
            'bank_name'                     => 'nullable|string|max:100',               // 銀行名
            'bank_code'                     => 'nullable|digits:4',                     // 銀行番号（4桁数字のみ）
            'branch_name'                   => 'nullable|string|max:100',               // 支店名
            'branch_code'                   => 'nullable|digits:3',                     // 支店番号（3桁数字のみ）
            'account_type'                  => 'nullable|in:1,2',                       // 口座種別（1が普通、2が当座）
            'account_number'                => 'nullable|digits:7',                     // 口座番号（7桁数字のみ）
            'account_holder_name'           => 'nullable|string|max:255',               // 口座名義
            'pickup_location_post_code'     => 'nullable|regex:/^[0-9]{3}-[0-9]{4}$/',  // 集荷場所郵便番号（7桁で-あり）
            'pickup_location_main_address'  => 'nullable|string|max:255',               // 集荷場所住所
            'pickup_location_building_name' => 'nullable|string|max:255',               // 集荷場所建物名
            'business_days'                 => 'nullable|string|max:255',               // 営業日
            'payment_date'                  => 'nullable|string|max:255',               // 支払日
            'payment_closing_date'          => 'nullable|string|max:255',               // 支払締め日
        ];
    }

    public function attributes()
    {
        return [
            'management_code' => '管理コード',
            'hatsu_ninushi_code' => '発荷主コード',
            'company_name' => '会社名',
            'manager' => '担当者',
            'manager_telephone_number' => '担当者連絡先',
            'selling_places' => '販売先',
            'post_code' => '郵便番号',
            'main_address' => '住所（番地まで）',
            'building_name' => '建物名（部屋番号）',
            'bank_name' => '銀行名',
            'bank_code' => '銀行番号',
            'branch_name' => '支店名',
            'branch_code' => '支店番号',
            'account_type' => '口座種別',
            'account_number' => '口座番号',
            'account_holder_name' => '口座名義',
            'pickup_location_post_code' => '集荷場所 郵便番号',
            'pickup_location_main_address' => '集荷場所 住所',
            'pickup_location_building_name' => '集荷場所 建物名',
            'business_days' => '営業日',
            'payment_date' => '支払日',
            'payment_closing_date' => '支払締め日',
        ];
    }

    public function messages()
    {
        return [
            'required' => ':attributeは必須項目です。',
            'post_code.regex' => ':attributeは半角数字3桁、ハイフン、半角数字4桁の形式（例: 123-4567）で入力してください。',
            'pickup_location_post_code.regex' => ':attributeは半角数字3桁、ハイフン、半角数字4桁の形式（例: 123-4567）で入力してください。',
            'bank_code.digits' => ':attributeは半角数字4桁で入力してください。',
            'branch_code.digits' => ':attributeは半角数字3桁で入力してください。',
            'account_number.digits' => ':attributeは半角数字7桁で入力してください。',
            'manager_telephone_number.regex' => ':attributeは数字で入力してください。',
        ];
    }
}