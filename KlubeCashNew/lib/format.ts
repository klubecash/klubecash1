export const money = (value: string | number | null | undefined) =>
  new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(
    Number(value ?? 0),
  );

export const moneyFromCents = (value: number | null | undefined) =>
  money(Number(value ?? 0) / 100);

export const number = (value: string | number | null | undefined) =>
  new Intl.NumberFormat("pt-BR").format(Number(value ?? 0));

export const dateTime = (value: string | null | undefined) => {
  if (!value) return "—";
  const normalized = value.includes("T") ? value : value.replace(" ", "T");
  const parsed = new Date(normalized);
  return Number.isNaN(parsed.valueOf())
    ? value
    : new Intl.DateTimeFormat("pt-BR", {
        dateStyle: "short",
        timeStyle: "short",
      }).format(parsed);
};

export const monthLabel = (value: string) => {
  const [year, month] = value.split("-");
  return new Intl.DateTimeFormat("pt-BR", { month: "short" })
    .format(new Date(Number(year), Number(month) - 1, 1))
    .replace(".", "");
};
