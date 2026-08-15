"use client";

import { useEffect } from "react";

const THEME_STORAGE_KEY = "klubecash-theme";
type Theme = "light" | "dark";

function isTheme(value: string | null): value is Theme {
  return value === "light" || value === "dark";
}

function focusableElements(container: HTMLElement): HTMLElement[] {
  return Array.from(
    container.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((element) => !element.hidden && element.getAttribute("aria-hidden") !== "true");
}

export function HomepageInteractions() {
  useEffect(() => {
    const root = document.documentElement;
    const cleanups: Array<() => void> = [];
    const reducedMotionQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
    const systemThemeQuery = window.matchMedia("(prefers-color-scheme: dark)");
    const desktopQuery = window.matchMedia("(min-width: 960px)");
    let storedTheme: Theme | null = null;

    root.classList.add("js");

    try {
      const storedValue = window.localStorage.getItem(THEME_STORAGE_KEY);
      storedTheme = isTheme(storedValue) ? storedValue : null;
    } catch {
      storedTheme = null;
    }

    let hasManualTheme = storedTheme !== null;

    const updateThemeControls = (theme: Theme) => {
      const isDark = theme === "dark";
      const toggle = document.getElementById("themeToggle");
      const themeColor = document.getElementById("themeColor");

      if (toggle) {
        toggle.setAttribute("aria-pressed", String(isDark));
        toggle.setAttribute("aria-label", isDark ? "Ativar modo claro" : "Ativar modo noturno");
        toggle.querySelector(".theme-icon-sun")?.classList.toggle("is-visible", isDark);
        toggle.querySelector(".theme-icon-moon")?.classList.toggle("is-visible", !isDark);
      }

      if (themeColor) {
        themeColor.setAttribute("content", isDark ? "#0B0D12" : "#FFF8F3");
      }
    };

    const applyTheme = (theme: Theme, persist: boolean) => {
      root.setAttribute("data-theme", theme);
      root.style.colorScheme = theme;
      updateThemeControls(theme);

      if (persist) {
        hasManualTheme = true;
        try {
          window.localStorage.setItem(THEME_STORAGE_KEY, theme);
        } catch {
          // O tema continua válido durante a sessão mesmo sem storage.
        }
      }
    };

    const currentTheme = root.getAttribute("data-theme");
    applyTheme(isTheme(currentTheme) ? currentTheme : systemThemeQuery.matches ? "dark" : "light", false);

    const themeToggle = document.getElementById("themeToggle");
    const onThemeToggle = () => {
      applyTheme(root.getAttribute("data-theme") === "dark" ? "light" : "dark", true);
    };
    themeToggle?.addEventListener("click", onThemeToggle);
    cleanups.push(() => themeToggle?.removeEventListener("click", onThemeToggle));

    const onSystemThemeChange = (event: MediaQueryListEvent) => {
      if (!hasManualTheme) {
        applyTheme(event.matches ? "dark" : "light", false);
      }
    };
    systemThemeQuery.addEventListener("change", onSystemThemeChange);
    cleanups.push(() => systemThemeQuery.removeEventListener("change", onSystemThemeChange));

    const header = document.getElementById("mainHeader");
    let headerFrame = 0;
    const updateHeader = () => {
      headerFrame = 0;
      header?.classList.toggle("is-scrolled", window.scrollY > 12);
    };
    const onHeaderScroll = () => {
      if (!headerFrame) {
        headerFrame = window.requestAnimationFrame(updateHeader);
      }
    };
    updateHeader();
    window.addEventListener("scroll", onHeaderScroll, { passive: true });
    cleanups.push(() => {
      window.removeEventListener("scroll", onHeaderScroll);
      if (headerFrame) window.cancelAnimationFrame(headerFrame);
    });

    const mobileButton = document.getElementById("mobileMenuBtn") as HTMLButtonElement | null;
    const mobileMenu = document.getElementById("mobileMenu") as HTMLElement | null;
    const userButton = document.getElementById("userMenuBtn") as HTMLButtonElement | null;
    const userDropdown = document.getElementById("userDropdown") as HTMLElement | null;
    let mobileOpen = false;
    let userOpen = false;
    let previousOverflow = "";

    const renderMobile = () => {
      if (!mobileButton || !mobileMenu) return;
      mobileButton.setAttribute("aria-expanded", String(mobileOpen));
      mobileButton.setAttribute("aria-label", mobileOpen ? "Fechar menu principal" : "Abrir menu principal");
      mobileMenu.setAttribute("aria-hidden", String(!mobileOpen));
      mobileMenu.hidden = !mobileOpen;
      mobileMenu.classList.toggle("show", mobileOpen);
      mobileMenu.classList.toggle("is-open", mobileOpen);
      mobileButton.classList.toggle("is-open", mobileOpen);

      if (mobileOpen) {
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        document.body.classList.add("menu-open");
        root.classList.add("menu-open");
      } else {
        document.body.style.overflow = previousOverflow;
        document.body.classList.remove("menu-open");
        root.classList.remove("menu-open");
      }
    };

    const renderUser = () => {
      if (!userButton || !userDropdown) return;
      userButton.setAttribute("aria-expanded", String(userOpen));
      userDropdown.setAttribute("aria-hidden", String(!userOpen));
      userDropdown.hidden = !userOpen;
      userDropdown.classList.toggle("show", userOpen);
      userDropdown.classList.toggle("is-open", userOpen);
      userButton.classList.toggle("is-open", userOpen);
    };

    const closeMobile = (restoreFocus = false) => {
      const wasOpen = mobileOpen;
      mobileOpen = false;
      renderMobile();
      if (wasOpen && restoreFocus) mobileButton?.focus();
    };

    const closeUser = (restoreFocus = false) => {
      const wasOpen = userOpen;
      userOpen = false;
      renderUser();
      if (wasOpen && restoreFocus) userButton?.focus();
    };

    const openMobile = () => {
      closeUser(false);
      mobileOpen = true;
      renderMobile();
      if (mobileMenu) {
        const items = focusableElements(mobileMenu);
        if (items[0]) items[0].focus();
      }
    };

    const userItems = userDropdown ? focusableElements(userDropdown) : [];
    const focusUserItem = (index: number) => {
      if (!userItems.length) return;
      userItems[(index + userItems.length) % userItems.length]?.focus();
    };
    const openUser = (focusIndex?: number) => {
      closeMobile(false);
      userOpen = true;
      renderUser();
      if (typeof focusIndex === "number") focusUserItem(focusIndex);
    };

    const onMobileClick = () => (mobileOpen ? closeMobile(true) : openMobile());
    mobileButton?.addEventListener("click", onMobileClick);
    cleanups.push(() => mobileButton?.removeEventListener("click", onMobileClick));

    const onMobileMenuClick = (event: MouseEvent) => {
      const link = (event.target as Element | null)?.closest("a[href]");
      if (link && mobileMenu?.contains(link)) closeMobile(true);
    };
    mobileMenu?.addEventListener("click", onMobileMenuClick);
    cleanups.push(() => mobileMenu?.removeEventListener("click", onMobileMenuClick));

    const onUserClick = () => (userOpen ? closeUser(true) : openUser(0));
    userButton?.addEventListener("click", onUserClick);
    cleanups.push(() => userButton?.removeEventListener("click", onUserClick));

    const onUserButtonKeydown = (event: KeyboardEvent) => {
      if (event.key === "ArrowDown" || event.key === "Home") {
        event.preventDefault();
        openUser(0);
      } else if (event.key === "ArrowUp" || event.key === "End") {
        event.preventDefault();
        openUser(userItems.length - 1);
      } else if (event.key === "Escape" && userOpen) {
        event.preventDefault();
        closeUser(true);
      }
    };
    userButton?.addEventListener("keydown", onUserButtonKeydown);
    cleanups.push(() => userButton?.removeEventListener("keydown", onUserButtonKeydown));

    const onUserDropdownKeydown = (event: KeyboardEvent) => {
      const currentIndex = userItems.indexOf(document.activeElement as HTMLElement);
      if (event.key === "ArrowDown") {
        event.preventDefault();
        focusUserItem(currentIndex < 0 ? 0 : currentIndex + 1);
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        focusUserItem(currentIndex < 0 ? userItems.length - 1 : currentIndex - 1);
      } else if (event.key === "Home") {
        event.preventDefault();
        focusUserItem(0);
      } else if (event.key === "End") {
        event.preventDefault();
        focusUserItem(userItems.length - 1);
      } else if (event.key === "Escape") {
        event.preventDefault();
        closeUser(true);
      } else if (event.key === "Tab") {
        window.setTimeout(() => closeUser(false), 0);
      }
    };
    userDropdown?.addEventListener("keydown", onUserDropdownKeydown);
    cleanups.push(() => userDropdown?.removeEventListener("keydown", onUserDropdownKeydown));

    const onUserDropdownClick = (event: MouseEvent) => {
      const item = (event.target as Element | null)?.closest("a[href], button");
      if (item && userDropdown?.contains(item)) {
        closeUser((item.getAttribute("href") ?? "").startsWith("#"));
      }
    };
    userDropdown?.addEventListener("click", onUserDropdownClick);
    cleanups.push(() => userDropdown?.removeEventListener("click", onUserDropdownClick));

    const onPointerDown = (event: PointerEvent) => {
      const target = event.target as Node;
      if (mobileOpen && !mobileMenu?.contains(target) && !mobileButton?.contains(target)) closeMobile(false);
      if (userOpen && !userDropdown?.contains(target) && !userButton?.contains(target)) closeUser(false);
    };
    document.addEventListener("pointerdown", onPointerDown);
    cleanups.push(() => document.removeEventListener("pointerdown", onPointerDown));

    const onDocumentKeydown = (event: KeyboardEvent) => {
      if (event.key !== "Escape") return;
      if (mobileOpen) {
        event.preventDefault();
        closeMobile(true);
      } else if (userOpen) {
        event.preventDefault();
        closeUser(true);
      }
    };
    document.addEventListener("keydown", onDocumentKeydown);
    cleanups.push(() => document.removeEventListener("keydown", onDocumentKeydown));

    const onDesktopChange = (event: MediaQueryListEvent) => {
      if (event.matches && mobileOpen) closeMobile(true);
    };
    desktopQuery.addEventListener("change", onDesktopChange);
    cleanups.push(() => desktopQuery.removeEventListener("change", onDesktopChange));

    const onWindowResize = () => {
      if (userOpen) {
        closeUser(Boolean(document.activeElement && userDropdown?.contains(document.activeElement)));
      }
    };
    window.addEventListener("resize", onWindowResize, { passive: true });
    cleanups.push(() => window.removeEventListener("resize", onWindowResize));

    renderMobile();
    renderUser();

    const getHeaderHeight = () => Math.ceil(header?.getBoundingClientRect().height ?? 0);
    const anchorLinks = Array.from(document.querySelectorAll<HTMLAnchorElement>('a[href^="#"]'));
    const anchorHandlers = anchorLinks.map((link) => {
      const handler = (event: MouseEvent) => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const href = link.getAttribute("href");
        if (!href || href === "#") {
          event.preventDefault();
          return;
        }
        const target = document.getElementById(decodeURIComponent(href.slice(1)));
        if (!target) return;
        event.preventDefault();
        const top = target.getBoundingClientRect().top + window.scrollY - getHeaderHeight();
        window.scrollTo({ top: Math.max(0, top), behavior: reducedMotionQuery.matches ? "auto" : "smooth" });
      };
      link.addEventListener("click", handler);
      return () => link.removeEventListener("click", handler);
    });
    cleanups.push(...anchorHandlers);

    const sections = ["como-funciona", "vantagens", "parceiros", "sobre"]
      .map((id) => document.getElementById(id))
      .filter((section): section is HTMLElement => section !== null);
    const navigationLinks = Array.from(
      document.querySelectorAll<HTMLAnchorElement>('.nav-link[href^="#"], .mobile-nav-link[href^="#"]'),
    );
    let activeFrame = 0;
    const updateActiveSection = () => {
      activeFrame = 0;
      const activationLine = getHeaderHeight() + Math.min(window.innerHeight * 0.28, 220);
      let activeId: string | null = null;
      sections.forEach((section) => {
        if (section.getBoundingClientRect().top <= activationLine) activeId = section.id;
      });
      navigationLinks.forEach((link) => {
        const active = link.getAttribute("href") === `#${activeId}`;
        link.classList.toggle("active", active);
        link.classList.toggle("is-active", active);
        if (active) link.setAttribute("aria-current", "location");
        else link.removeAttribute("aria-current");
      });
    };
    const scheduleActiveSection = () => {
      if (!activeFrame) activeFrame = window.requestAnimationFrame(updateActiveSection);
    };
    updateActiveSection();
    window.addEventListener("scroll", scheduleActiveSection, { passive: true });
    window.addEventListener("resize", scheduleActiveSection, { passive: true });
    cleanups.push(() => {
      window.removeEventListener("scroll", scheduleActiveSection);
      window.removeEventListener("resize", scheduleActiveSection);
      if (activeFrame) window.cancelAnimationFrame(activeFrame);
    });

    const revealElements = Array.from(document.querySelectorAll<HTMLElement>(".fade-in"));
    const revealAll = () => {
      root.classList.remove("reveal-ready");
      revealElements.forEach((element) => element.classList.add("is-visible", "visible"));
    };
    let observer: IntersectionObserver | null = null;
    if (reducedMotionQuery.matches || !("IntersectionObserver" in window)) {
      revealAll();
    } else {
      observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add("is-visible", "visible");
              observer?.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.12, rootMargin: "0px 0px -8% 0px" },
      );
      root.classList.add("reveal-ready");
      revealElements.forEach((element) => observer?.observe(element));
    }

    const onReducedMotionChange = (event: MediaQueryListEvent) => {
      if (event.matches) {
        observer?.disconnect();
        revealAll();
      }
    };
    reducedMotionQuery.addEventListener("change", onReducedMotionChange);
    cleanups.push(() => {
      reducedMotionQuery.removeEventListener("change", onReducedMotionChange);
      observer?.disconnect();
    });

    return () => {
      cleanups.forEach((cleanup) => cleanup());
      document.body.style.overflow = previousOverflow;
      document.body.classList.remove("menu-open");
      root.classList.remove("menu-open", "reveal-ready");
    };
  }, []);

  return null;
}
