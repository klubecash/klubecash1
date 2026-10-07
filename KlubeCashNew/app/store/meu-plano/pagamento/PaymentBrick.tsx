"use client";

import { useEffect, useRef, useState } from "react";
import { storeFetch } from "@/lib/client-api";

declare global {
  interface Window {
    MercadoPago?: new (publicKey: string, options?: { locale?: string }) => {
      bricks: () => { create: (name: string, container: string, options: Record<string, unknown>) => Promise<{ unmount?: () => void }> };
    };
  }
}

type PaymentResult = {
  invoiceId: number;
  paymentId: string;
  status: "pending" | "paid" | "failed" | "approved" | "rejected" | "cancelled";
  statusDetail?: string | null;
  pixQrCode?: string | null;
  pixCopyPaste?: string | null;
};

type Props = {
  publicKey: string;
  amountCents: number;
  invoiceId: number;
  csrfToken: string;
  onResult: (result: PaymentResult) => void;
};

const requestKey = () => typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;

function loadSdk(): Promise<void> {
  if (typeof window === "undefined") return Promise.reject(new Error("Checkout indisponível."));
  if (window.MercadoPago) return Promise.resolve();
  return new Promise((resolve, reject) => {
    const existing = document.querySelector<HTMLScriptElement>('script[data-mp-sdk="checkout-bricks"]');
    if (existing) {
      existing.addEventListener("load", () => resolve(), { once: true });
      existing.addEventListener("error", () => reject(new Error("Não foi possível carregar o Mercado Pago.")), { once: true });
      return;
    }
    const script = document.createElement("script");
    script.src = "https://sdk.mercadopago.com/js/v2";
    script.async = true;
    script.dataset.mpSdk = "checkout-bricks";
    script.onload = () => resolve();
    script.onerror = () => reject(new Error("Não foi possível carregar o Mercado Pago."));
    document.head.appendChild(script);
  });
}

export default function PaymentBrick({ publicKey, amountCents, invoiceId, csrfToken, onResult }: Props) {
  const [error, setError] = useState<string | null>(null);
  const brickRef = useRef<{ unmount?: () => void } | null>(null);
  const configurationError = !publicKey || amountCents <= 0 ? "O Checkout Transparente ainda não está configurado para esta conta." : null;

  useEffect(() => {
    let cancelled = false;
    if (configurationError) return;
    loadSdk()
      .then(async () => {
        if (cancelled || !window.MercadoPago) return;
        const mp = new window.MercadoPago(publicKey, { locale: "pt-BR" });
        const bricks = mp.bricks();
        brickRef.current = await bricks.create("payment", "paymentBrick_container", {
          initialization: { amount: amountCents / 100 },
          customization: {
            paymentMethods: {
              creditCard: "all",
              debitCard: "all",
              ticket: "all",
              bankTransfer: "all",
              mercadoPago: "all",
            },
          },
          callbacks: {
            onReady: () => setError(null),
            onError: () => setError("O formulário de pagamento não pôde ser carregado. Tente novamente."),
            onSubmit: async (formData: unknown) => {
              const data = (formData && typeof formData === "object" ? formData : {}) as Record<string, unknown>;
              const result = await storeFetch<PaymentResult>("subscription/payment", {
                method: "POST",
                headers: { "X-CSRF-Token": csrfToken, "X-Idempotency-Key": requestKey() },
                body: JSON.stringify({
                  invoiceId,
                  token: data.token ?? "",
                  paymentMethodId: data.payment_method_id ?? data.paymentMethodId ?? "",
                  issuerId: data.issuer_id ?? data.issuerId ?? null,
                  installments: data.installments ?? 1,
                  deviceId: data.device_id ?? data.deviceId ?? null,
                  csrfToken,
                }),
              });
              onResult(result);
              return result;
            },
          },
        });
      })
      .catch((reason: unknown) => {
        if (!cancelled) setError(reason instanceof Error ? reason.message : "Não foi possível iniciar o checkout.");
      });
    return () => {
      cancelled = true;
      brickRef.current?.unmount?.();
      brickRef.current = null;
    };
  }, [amountCents, configurationError, csrfToken, invoiceId, onResult, publicKey]);

  return <>
    <div id="paymentBrick_container" className="store-payment-brick" />
    {(configurationError || error) && <p className="store-error" role="alert">{configurationError || error}</p>}
  </>;
}
