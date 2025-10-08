import { useState, useEffect } from "react";
import { NavLink, useNavigate } from "react-router-dom";
import {
  LayoutDashboard,
  ShoppingCart,
  Receipt,
  Clock,
  QrCode,
  History,
  Users,
  UserCircle,
  Store,
  Upload,
  LogOut,
} from "lucide-react";
import { authService } from "@/services/authService";
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";

const menuItems = [
  { title: "Dashboard", url: "/", icon: LayoutDashboard, section: "principal" },
  { title: "Nova Venda", url: "/nova-venda", icon: ShoppingCart, section: "principal" },
  { title: "Transações", url: "/transacoes", icon: Receipt, section: "principal" },
  { title: "Pendentes de Pagamento", url: "/pendentes", icon: Clock, section: "principal" },
  { title: "Pagamentos (Pix)", url: "/pagamentos-pix", icon: QrCode, section: "principal" },
  { title: "Histórico de Pagamentos", url: "/historico-pagamentos", icon: History, section: "principal" },
  { title: "Funcionários", url: "/funcionarios", icon: Users, section: "principal" },
  { title: "Perfil", url: "/perfil", icon: UserCircle, section: "conta" },
  { title: "Detalhes da Loja", url: "/detalhes-loja", icon: Store, section: "conta" },
  { title: "Importação em Lote", url: "/importacao", icon: Upload, section: "conta" },
];

export function AppSidebar() {
  const { state } = useSidebar();
  const navigate = useNavigate();
  const [showLogoutDialog, setShowLogoutDialog] = useState(false);
  const [userName, setUserName] = useState("Loja Exemplo");
  const [userType, setUserType] = useState("Lojista");
  const isCollapsed = state === "collapsed";

  const principalItems = menuItems.filter((item) => item.section === "principal");
  const contaItems = menuItems.filter((item) => item.section === "conta");

  useEffect(() => {
    const user = authService.getCurrentUser();
    if (user) {
      setUserName(user.storeName || user.name);
      setUserType(user.type === 'loja' ? 'Lojista' : user.type === 'funcionario' ? 'Funcionário' : 'Usuário');
    }
  }, []);

  const handleLogout = async () => {
    await authService.logout();
    setShowLogoutDialog(false);
    navigate("/login");
  };

  return (
    <>
      <Sidebar collapsible="icon" className="border-r border-sidebar-border">
        <div className="flex flex-col h-full">
          {/* Logo and user section */}
          <div className="p-4 border-b border-sidebar-border">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-8 h-8 rounded-lg bg-primary flex items-center justify-center text-primary-foreground font-bold text-sm">
                KC
              </div>
              {!isCollapsed && (
                <span className="font-semibold text-foreground">KlubeCash</span>
              )}
            </div>
            
            {!isCollapsed && (
              <div className="flex items-center gap-3 p-3 rounded-lg bg-muted/50">
                <Avatar className="h-10 w-10">
                  <AvatarFallback className="bg-primary text-primary-foreground">
                    {userName.substring(0, 2).toUpperCase()}
                  </AvatarFallback>
                </Avatar>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-foreground truncate">
                    {userName}
                  </p>
                  <p className="text-xs text-muted-foreground">{userType}</p>
                </div>
              </div>
            )}
          </div>

          {/* Menu items */}
          <SidebarContent>
            <SidebarGroup>
              <SidebarGroupLabel>Principal</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  {principalItems.map((item) => (
                    <SidebarMenuItem key={item.title}>
                      <SidebarMenuButton asChild tooltip={item.title}>
                        <NavLink
                          to={item.url}
                          end={item.url === "/"}
                          className={({ isActive }) =>
                            isActive
                              ? "bg-primary/10 text-primary font-medium border-l-2 border-primary"
                              : "hover:bg-muted"
                          }
                        >
                          <item.icon className="h-4 w-4" />
                          <span>{item.title}</span>
                        </NavLink>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  ))}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup>
              <SidebarGroupLabel>Conta</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  {contaItems.map((item) => (
                    <SidebarMenuItem key={item.title}>
                      <SidebarMenuButton asChild tooltip={item.title}>
                        <NavLink
                          to={item.url}
                          className={({ isActive }) =>
                            isActive
                              ? "bg-primary/10 text-primary font-medium border-l-2 border-primary"
                              : "hover:bg-muted"
                          }
                        >
                          <item.icon className="h-4 w-4" />
                          <span>{item.title}</span>
                        </NavLink>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  ))}
                  
                  <SidebarMenuItem>
                    <SidebarMenuButton
                      tooltip="Sair"
                      onClick={() => setShowLogoutDialog(true)}
                      className="text-destructive hover:text-destructive hover:bg-destructive/10"
                    >
                      <LogOut className="h-4 w-4" />
                      <span>Sair</span>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          </SidebarContent>
        </div>
      </Sidebar>

      <AlertDialog open={showLogoutDialog} onOpenChange={setShowLogoutDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Confirmar saída</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja sair do portal? Você precisará fazer login novamente.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={handleLogout} className="bg-destructive hover:bg-destructive/90">
              Sair
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
