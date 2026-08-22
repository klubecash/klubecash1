"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Building2, KeyRound, MapPin, Save } from "lucide-react";
import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { storeFetch } from "@/lib/client-api";
import { dateTime } from "@/lib/format";
import { ErrorState, LoadingState } from "@/components/store/PageState";
import { useStoreContext } from "@/components/store/StoreProviders";

type Profile = {
  dataState: "ready";
  generatedAt: string;
  company: {
    tradeName: string;
    legalName: string;
    cnpj: string;
    email: string;
    phone: string;
    website: string;
    description: string;
    customerCashbackPercentage: number;
    status: string;
    createdAt: string;
  };
  address: {
    postalCode: string;
    street: string;
    number: string;
    complement: string;
    neighborhood: string;
    city: string;
    state: string;
  };
};

const contactSchema = z.object({
  phone: z.string().refine((value) => /^\d{10,11}$/.test(value.replace(/\D/g, "")), "Informe um telefone com DDD."),
  website: z.union([z.literal(""), z.url("Informe uma URL completa.")]),
  description: z.string().max(1000, "Use no máximo 1000 caracteres."),
});
const addressSchema = z.object({
  postalCode: z.string().refine((value) => /^\d{8}$/.test(value.replace(/\D/g, "")), "Informe um CEP válido."),
  street: z.string().trim().min(1, "Campo obrigatório."),
  number: z.string().trim().min(1, "Campo obrigatório."),
  complement: z.string(),
  neighborhood: z.string().trim().min(1, "Campo obrigatório."),
  city: z.string().trim().min(1, "Campo obrigatório."),
  state: z.string().trim().length(2, "Use a sigla com duas letras."),
});
const passwordSchema = z
  .object({
    currentPassword: z.string().min(1, "Informe sua senha atual."),
    newPassword: z.string().min(8, "Use pelo menos oito caracteres."),
    confirmation: z.string().min(8),
  })
  .refine((data) => data.newPassword === data.confirmation, {
    path: ["confirmation"],
    message: "As senhas não coincidem.",
  });

export default function ProfilePage() {
  const context = useStoreContext();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ["profile"], queryFn: () => storeFetch<Profile>("profile") });
  const contact = useForm<z.infer<typeof contactSchema>>({
    resolver: zodResolver(contactSchema),
    defaultValues: { phone: "", website: "", description: "" },
  });
  const address = useForm<z.infer<typeof addressSchema>>({
    resolver: zodResolver(addressSchema),
    defaultValues: { postalCode: "", street: "", number: "", complement: "", neighborhood: "", city: "", state: "" },
  });
  const password = useForm<z.infer<typeof passwordSchema>>({
    resolver: zodResolver(passwordSchema),
    defaultValues: { currentPassword: "", newPassword: "", confirmation: "" },
  });

  useEffect(() => {
    if (!query.data) return;
    contact.reset({
      phone: query.data.company.phone,
      website: query.data.company.website,
      description: query.data.company.description,
    });
    address.reset(query.data.address);
  }, [query.data, contact, address]);

  const contactMutation = useMutation({
    mutationFn: (values: z.infer<typeof contactSchema>) => mutate("profile/contact", values),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["profile"] }),
  });
  const addressMutation = useMutation({
    mutationFn: (values: z.infer<typeof addressSchema>) => mutate("profile/address", values),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["profile"] }),
  });
  const passwordMutation = useMutation({
    mutationFn: (values: z.infer<typeof passwordSchema>) => mutate("profile/password", values),
    onSuccess: () => password.reset(),
  });
  function mutate(path: string, values: object) {
    return storeFetch<{ updated: boolean }>(path, {
      method: "POST",
      headers: { "X-CSRF-Token": context.csrfToken },
      body: JSON.stringify({ ...values, csrfToken: context.csrfToken }),
    });
  }

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState message={query.error?.message ?? "Erro inesperado."} retry={() => query.refetch()} />;
  const profile = query.data;

  return (
    <div className="store-page store-stack">
      <section className="store-page-head">
        <div><h2>Perfil da loja</h2><p>Mantenha os dados comerciais, endereço e senha atualizados.</p></div>
      </section>
      <section className="store-panel">
        <div className="store-panel-head">
          <div><h3><Building2 size={17} /> Dados da empresa</h3><p>Informações cadastrais protegidas.</p></div>
          <span className={`store-status ${profile.company.status}`}>{profile.company.status}</span>
        </div>
        <div className="store-grid store-grid-3">
          <Info label="Nome fantasia" value={profile.company.tradeName} />
          <Info label="Razão social" value={profile.company.legalName} />
          <Info label="CNPJ" value={formatCnpj(profile.company.cnpj)} />
          <Info label="E-mail" value={profile.company.email} />
          <Info label="Cashback do cliente" value={`${profile.company.customerCashbackPercentage}%`} />
          <Info label="Cadastro" value={dateTime(profile.company.createdAt)} />
        </div>
      </section>
      <section className="store-grid store-grid-2">
        <form className="store-panel store-form" onSubmit={contact.handleSubmit((values) => contactMutation.mutate(values))}>
          <div className="store-panel-head"><div><h3>Contato e apresentação</h3><p>Dados utilizados pelo Klube Cash.</p></div></div>
          <FormField label="Telefone" error={contact.formState.errors.phone?.message}><input className="store-input" {...contact.register("phone")} /></FormField>
          <FormField label="Website" error={contact.formState.errors.website?.message}><input className="store-input" type="url" placeholder="https://" {...contact.register("website")} /></FormField>
          <FormField label="Descrição" error={contact.formState.errors.description?.message}><textarea className="store-textarea" {...contact.register("description")} /></FormField>
          <MutationMessage mutation={contactMutation} />
          <div className="store-form-actions"><button className="store-button store-button-primary" disabled={contactMutation.isPending}><Save size={16} /> Salvar informações</button></div>
        </form>
        <form className="store-panel store-form" onSubmit={address.handleSubmit((values) => addressMutation.mutate(values))}>
          <div className="store-panel-head"><div><h3><MapPin size={17} /> Endereço</h3><p>Localização comercial da loja.</p></div></div>
          <div className="store-form-grid">
            <FormField label="CEP" error={address.formState.errors.postalCode?.message}><input className="store-input" {...address.register("postalCode")} /></FormField>
            <FormField label="Estado" error={address.formState.errors.state?.message}><input className="store-input" maxLength={2} {...address.register("state")} /></FormField>
            <FormField label="Cidade" error={address.formState.errors.city?.message}><input className="store-input" {...address.register("city")} /></FormField>
            <FormField label="Bairro" error={address.formState.errors.neighborhood?.message}><input className="store-input" {...address.register("neighborhood")} /></FormField>
            <FormField label="Logradouro" error={address.formState.errors.street?.message}><input className="store-input" {...address.register("street")} /></FormField>
            <FormField label="Número" error={address.formState.errors.number?.message}><input className="store-input" {...address.register("number")} /></FormField>
            <FormField label="Complemento"><input className="store-input" {...address.register("complement")} /></FormField>
          </div>
          <MutationMessage mutation={addressMutation} />
          <div className="store-form-actions"><button className="store-button store-button-primary" disabled={addressMutation.isPending}><Save size={16} /> Salvar endereço</button></div>
        </form>
      </section>
      <form className="store-panel store-form" style={{ maxWidth: 750 }} onSubmit={password.handleSubmit((values) => passwordMutation.mutate(values))}>
        <div className="store-panel-head"><div><h3><KeyRound size={17} /> Alterar senha</h3><p>Use pelo menos oito caracteres.</p></div></div>
        <FormField label="Senha atual" error={password.formState.errors.currentPassword?.message}><input className="store-input" type="password" {...password.register("currentPassword")} /></FormField>
        <div className="store-form-grid">
          <FormField label="Nova senha" error={password.formState.errors.newPassword?.message}><input className="store-input" type="password" {...password.register("newPassword")} /></FormField>
          <FormField label="Confirmar nova senha" error={password.formState.errors.confirmation?.message}><input className="store-input" type="password" {...password.register("confirmation")} /></FormField>
        </div>
        <MutationMessage mutation={passwordMutation} />
        <div className="store-form-actions"><button className="store-button store-button-primary" disabled={passwordMutation.isPending}>Alterar senha</button></div>
      </form>
    </div>
  );
}

function FormField({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return <label className="store-field"><span>{label}</span>{children}{error && <small className="store-error">{error}</small>}</label>;
}
function Info({ label, value }: { label: string; value: string }) {
  return <div className="store-customer-card"><p>{label}</p><h4 style={{ marginTop: 6 }}>{value || "—"}</h4></div>;
}
function MutationMessage({ mutation }: { mutation: { isSuccess: boolean; isError: boolean; error: Error | null } }) {
  if (mutation.isSuccess) return <div className="store-alert store-alert-success">Alterações salvas com sucesso.</div>;
  if (mutation.isError) return <div className="store-alert store-alert-error">{mutation.error?.message}</div>;
  return null;
}
function formatCnpj(value: string) {
  const digits = value.replace(/\D/g, "");
  return digits.length === 14 ? digits.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5") : value;
}
