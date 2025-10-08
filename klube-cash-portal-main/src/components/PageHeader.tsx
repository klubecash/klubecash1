import { ReactNode } from "react";
import { Button } from "@/components/ui/button";
import { SidebarTrigger } from "@/components/ui/sidebar";

interface PageHeaderProps {
  title: string;
  subtitle?: string;
  action?: {
    label: string;
    onClick: () => void;
    icon?: ReactNode;
  };
}

export function PageHeader({ title, subtitle, action }: PageHeaderProps) {
  return (
    <div className="border-b border-border bg-background sticky top-0 z-10">
      <div className="flex items-center justify-between p-4 lg:p-6">
        <div className="flex items-center gap-4">
          <SidebarTrigger className="lg:hidden" />
          <div>
            <h1 className="text-2xl font-semibold text-foreground">{title}</h1>
            {subtitle && (
              <p className="text-sm text-muted-foreground mt-1">{subtitle}</p>
            )}
          </div>
        </div>
        {action && (
          <Button onClick={action.onClick} className="gap-2">
            {action.icon}
            <span className="hidden sm:inline">{action.label}</span>
          </Button>
        )}
      </div>
    </div>
  );
}
