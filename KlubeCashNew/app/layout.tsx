import type { Metadata, Viewport } from "next";
import { Inter } from "next/font/google";
import Script from "next/script";
import type { ReactNode } from "react";
import "./homepage.css";

const inter = Inter({
  subsets: ["latin"],
  weight: ["300", "400", "500", "600", "700", "800"],
  display: "swap",
  variable: "--font-inter",
});

export const metadata: Metadata = {
  description: "Klube Cash - O programa de cashback mais inteligente do Brasil. Receba dinheiro de volta em todas as suas compras. Cadastre-se grátis e comece a economizar hoje mesmo!",
  keywords: ["cashback", "dinheiro de volta", "economia", "programa de fidelidade", "compras online", "desconto", "lojas parceiras"],
  authors: [{ name: "Klube Cash" }],
  robots: { index: true, follow: true },
  icons: { icon: "/assets/images/icons/KlubeCashLOGO.ico" },
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
};

const themeBootstrap = `
(function (document, window) {
  var root = document.documentElement;
  var savedTheme = null;
  var prefersDark = false;
  root.classList.add('js');
  try {
    prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  } catch (error) {
    prefersDark = false;
  }
  try {
    savedTheme = window.localStorage.getItem('klubecash-theme');
  } catch (error) {
    savedTheme = null;
  }
  var theme = savedTheme === 'light' || savedTheme === 'dark'
    ? savedTheme
    : (prefersDark ? 'dark' : 'light');
  root.setAttribute('data-theme', theme);
  root.style.colorScheme = theme;
}(document, window));
`;

export default function RootLayout({ children }: Readonly<{ children: ReactNode }>) {
  return (
    <html lang="pt-BR" data-theme="light" suppressHydrationWarning>
      <head>
        <meta
          name="theme-color"
          id="themeColor"
          content="#FFF8F3"
          data-light="#FFF8F3"
          data-dark="#0B0D12"
        />
      </head>
      <body className={`${inter.variable} home-page`}>
        <Script id="klubecash-theme-bootstrap" strategy="beforeInteractive">
          {themeBootstrap}
        </Script>
        {children}
      </body>
    </html>
  );
}
