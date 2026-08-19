import { Component, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import { MerchantPortalSessionService } from '../../core/merchant-portal-session.service';

@Component({
  selector: 'app-merchant-shell',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <div class="merchant-shell">
      <aside class="merchant-sidebar" [class.open]="menuOpen()">
        <div class="merchant-brand"><span>M</span><strong>MicroBiz</strong></div>
        <div class="workspace-label">Merchant workspace</div>
        <nav (click)="menuOpen.set(false)">
          <a routerLink="/merchant/dashboard" routerLinkActive="active"><span>⌂</span> Overview</a>
          <a routerLink="/merchant/transactions" routerLinkActive="active"><span>↔</span> Transactions</a>
          <a routerLink="/merchant/profile" routerLinkActive="active"><span>○</span> Business profile</a>
        </nav>
        <div class="sidebar-support">
          <span>Need assistance?</span>
          <strong>Contact merchant support</strong>
          <small>support&#64;microbiz.test</small>
        </div>
        <div class="sidebar-user">
          <div class="avatar">{{ initial }}</div>
          <div><strong>{{ session.session()?.businessName }}</strong><small>{{ session.session()?.accountNumber }}</small></div>
          <button type="button" title="Sign out" (click)="logout()">↪</button>
        </div>
      </aside>

      @if (menuOpen()) { <button class="menu-backdrop" type="button" aria-label="Close menu" (click)="menuOpen.set(false)"></button> }

      <section class="merchant-main">
        <header class="merchant-topbar">
          <button class="menu-toggle" type="button" (click)="menuOpen.set(true)" aria-label="Open menu">☰</button>
          <div class="topbar-context"><span>Merchant portal</span><strong>{{ session.session()?.businessName }}</strong></div>
          <div class="topbar-actions">
            <button type="button" class="notification" aria-label="Notifications">●</button>
            <div class="topbar-avatar">{{ initial }}</div>
          </div>
        </header>
        <main class="merchant-content"><router-outlet></router-outlet></main>
      </section>
    </div>
  `,
  styles: [`
    :host { display: block; min-height: 100vh; }
    .merchant-shell { min-height: 100vh; display: flex; background: #f4f6fa; }
    .merchant-sidebar { width: 250px; position: fixed; inset: 0 auto 0 0; background: #172052; color: white; padding: 1.4rem 1rem 1rem; display: flex; flex-direction: column; z-index: 30; }
    .merchant-brand { display: flex; align-items: center; gap: .65rem; padding: .1rem .55rem; font-size: 1.08rem; }
    .merchant-brand span { width: 2rem; height: 2rem; border-radius: .6rem; background: #ffcb45; color: #172052; display: grid; place-items: center; font-weight: 850; }
    .workspace-label { margin: 1.7rem .55rem .75rem; text-transform: uppercase; font-size: .62rem; color: #828dbb; letter-spacing: .12em; font-weight: 800; }
    nav { display: grid; gap: .3rem; }
    nav a { display: flex; align-items: center; gap: .75rem; color: #bec6e5; text-decoration: none; padding: .72rem .75rem; border-radius: .55rem; font-size: .84rem; font-weight: 650; }
    nav a span { width: 1.1rem; text-align: center; color: #95a2d2; }
    nav a.active, nav a:hover { background: rgba(255,255,255,.1); color: white; }
    nav a.active span { color: #ffcb45; }
    .sidebar-support { margin-top: auto; background: rgba(255,255,255,.065); border: 1px solid rgba(255,255,255,.09); border-radius: .7rem; padding: .85rem; display: grid; gap: .25rem; }
    .sidebar-support span { color: #9fa9d1; font-size: .68rem; }
    .sidebar-support strong { font-size: .76rem; }
    .sidebar-support small { color: #ffcf54; font-size: .65rem; }
    .sidebar-user { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: .55rem; margin-top: .8rem; padding: .8rem .35rem 0; border-top: 1px solid rgba(255,255,255,.12); min-width: 0; }
    .avatar, .topbar-avatar { width: 2rem; height: 2rem; display: grid; place-items: center; border-radius: 50%; background: #e7ebf8; color: #23366f; font-size: .72rem; font-weight: 800; }
    .sidebar-user div:nth-child(2) { display: grid; min-width: 0; }
    .sidebar-user strong { font-size: .7rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sidebar-user small { color: #99a3cc; font-size: .62rem; margin-top: .15rem; }
    .sidebar-user button { border: 0; color: #aeb7d7; background: transparent; font-size: 1rem; }
    .merchant-main { margin-left: 250px; flex: 1; min-width: 0; }
    .merchant-topbar { height: 70px; padding: 0 clamp(1.2rem, 3vw, 2.5rem); background: #fff; border-bottom: 1px solid #e2e6ef; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 20; }
    .topbar-context { display: grid; gap: .15rem; }
    .topbar-context span { color: #9197a9; font-size: .65rem; }
    .topbar-context strong { color: #26304d; font-size: .83rem; }
    .topbar-actions { display: flex; align-items: center; gap: .85rem; }
    .notification { border: 0; background: #f3f5f9; color: #e8a700; width: 2rem; height: 2rem; border-radius: 50%; font-size: .55rem; }
    .merchant-content { padding: clamp(1.25rem, 3vw, 2.5rem); max-width: 1440px; margin: 0 auto; }
    .menu-toggle { display: none; border: 0; background: transparent; color: #172052; font-size: 1.25rem; }
    .menu-backdrop { display: none; }
    @media (max-width: 820px) {
      .merchant-sidebar { transform: translateX(-100%); transition: transform .2s ease; box-shadow: 15px 0 50px rgba(16,24,66,.2); }
      .merchant-sidebar.open { transform: translateX(0); }
      .merchant-main { margin-left: 0; }
      .menu-toggle { display: block; }
      .topbar-context { display: none; }
      .menu-backdrop { display: block; position: fixed; inset: 0; z-index: 25; border: 0; background: rgba(11,17,46,.45); }
    }
  `],
})
export class MerchantShellComponent {
  menuOpen = signal(false);

  constructor(
    public readonly session: MerchantPortalSessionService,
    private readonly api: MerchantPortalApiService,
    private readonly router: Router,
  ) {}

  get initial(): string {
    return this.session.session()?.businessName.charAt(0).toUpperCase() || 'M';
  }

  logout(): void {
    this.api.endSession().subscribe({
      next: () => this.finishLogout(),
      error: () => this.finishLogout(),
    });
  }

  private finishLogout(): void {
    this.session.clear();
    void this.router.navigate(['/login/merchants']);
  }
}

