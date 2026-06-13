<?php

namespace Domains\Receiving\Http\Controllers;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\Receiving\Actions\ConfirmGoodsReceiptByBuyer;
use Domains\Receiving\Actions\ConfirmGoodsReceiptByRequester;
use Domains\Receiving\Actions\RecordGoodsReceipt;
use Domains\Receiving\Http\Requests\ConfirmGoodsReceiptRequest;
use Domains\Receiving\Http\Requests\RecordGoodsReceiptRequest;
use Domains\Receiving\Http\Resources\GoodsReceiptResource;
use Domains\Receiving\Models\GoodsReceipt;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class GoodsReceiptController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RecordGoodsReceipt $recordGoodsReceipt,
        private readonly ConfirmGoodsReceiptByRequester $confirmByRequester,
        private readonly ConfirmGoodsReceiptByBuyer $confirmByBuyer,
    ) {}

    public function index(PurchaseOrder $purchaseOrder): ResourceCollection
    {
        $this->authorize('view', $purchaseOrder);

        $receipts = GoodsReceipt::query()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('purchase_order_id', $purchaseOrder->id)
            ->with('lines')
            ->orderByDesc('recorded_at')
            ->get();

        return GoodsReceiptResource::collection($receipts);
    }

    public function store(RecordGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('recordGoodsReceipt', $purchaseOrder);

        try {
            $receipt = $this->recordGoodsReceipt->handle(
                purchaseOrder: $purchaseOrder,
                actor: $request->user(),
                payload: $request->validated(),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'lines' => [$e->getMessage()],
            ]);
        }

        return (new GoodsReceiptResource($receipt))->response()->setStatusCode(201);
    }

    public function show(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('view', $goodsReceipt);

        $goodsReceipt->load('lines');

        return new GoodsReceiptResource($goodsReceipt);
    }

    public function confirmRequester(ConfirmGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('confirmRequester', $goodsReceipt);

        try {
            $receipt = $this->confirmByRequester->handle(
                receipt: $goodsReceipt,
                actor: $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'goods_receipt' => [$e->getMessage()],
            ]);
        }

        return new GoodsReceiptResource($receipt);
    }

    public function confirmBuyer(ConfirmGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('confirmBuyer', $goodsReceipt);

        try {
            $receipt = $this->confirmByBuyer->handle(
                receipt: $goodsReceipt,
                actor: $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'goods_receipt' => [$e->getMessage()],
            ]);
        }

        return new GoodsReceiptResource($receipt);
    }
}
