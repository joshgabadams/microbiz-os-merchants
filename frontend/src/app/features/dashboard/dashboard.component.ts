import { Component } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive, Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <div class="shell">
      <aside class="sidebar">
        <div class="brand">
          <span class="brand-mark">M</span>
          <span class="brand-name">MicroBiz OS</span>
        </div>

        <nav>
          <div class="nav-group">
            <span class="nav-group-label">Agency Banking</span>
            <a routerLink="/agents" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><path d="M17 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9 4a4 4 0 1 1 0 8 4 4 0 0 1 0-8ZM19 8a2.5 2.5 0 0 1 0 5M17.5 20v-2a3.5 3.5 0 0 0-1.5-2.87"/></svg>
              Agents
            </a>
            <a routerLink="/complaints" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><path d="M5 3v18M5 4h11l-2 4 2 4H5"/></svg>
              Complaints
            </a>
            <a routerLink="/inspections" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><rect x="6" y="4" width="12" height="17" rx="1.5"/><path d="M9 3.5h6v2H9zM9 11l2 2 4-4"/></svg>
              Inspections
            </a>
          </div>

          <div class="nav-group">
            <span class="nav-group-label">Vault &amp; Till</span>
            <a routerLink="/vaults" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M12 9v1.5M12 13.5V15"/></svg>
              Vaults
            </a>
            <a routerLink="/tellers" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c0-3.6 3.1-6.5 7-6.5s7 2.9 7 6.5"/></svg>
              Tellers
            </a>
            <a routerLink="/float-transfer" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><path d="M4 8h13M13 4l4 4-4 4M20 16H7M11 12l-4 4 4 4"/></svg>
              Float Transfer
            </a>
            <a routerLink="/balancing" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><path d="M12 3v18M6 8l-3 5a3 3 0 0 0 6 0l-3-5ZM18 8l-3 5a3 3 0 0 0 6 0l-3-5ZM5 8h14M9 5h6"/></svg>
              Balancing
            </a>
            <a routerLink="/branch-eod" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4M9 15l2 2 4-4"/></svg>
              Branch EOD
            </a>
            <a routerLink="/call-over-report" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v4h4M9 12h6M9 16h6M9 8h2"/></svg>
              Call-Over Report
            </a>
            <a routerLink="/ledger-statement" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><path d="M6 3h12v18H6z"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>
              Ledger Statement
            </a>
            <a routerLink="/terminal-callup" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><rect x="5" y="2.5" width="14" height="19" rx="2.5"/><path d="M8.5 6.5h7M8 10h8v5H8zM9 18h.01M12 18h.01M15 18h.01"/></svg>
              Terminal Call-up
            </a>
          </div>

          <div class="nav-group">
            <span class="nav-group-label">Customers &amp; Products</span>
            <a routerLink="/customer-cash" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6.5 6.5v0M17.5 17.5v0"/></svg>
              Customer Cash
            </a>
            <a routerLink="/wallets" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v2"/><rect x="3" y="7" width="18" height="13" rx="2"/><circle cx="16" cy="14" r="1.4"/></svg>
              Wallets
            </a>
            <a routerLink="/merchants" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><path d="M3 9l1.5-5h15L21 9M4 9h16v10H4zM9 9v10M15 9v10"/></svg>
              Merchants
            </a>
            <a routerLink="/fixed-deposits" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><path d="M3 10 12 4l9 6M5 10v9h14v-9M9 19v-5h6v5"/></svg>
              Fixed Deposits
            </a>
          </div>

          <div class="nav-group">
            <span class="nav-group-label">Oversight</span>
            <a routerLink="/approvals" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
              Approvals
            </a>
            <a routerLink="/tessa" routerLinkActive="active">
              <svg viewBox="0 0 24 24"><path d="M4 19V10M10 19V5M16 19v-7M20 19V3"/></svg>
              TESSA
            </a>
          </div>
        </nav>

        <div class="user">
          <div class="user-avatar">{{ initial() }}</div>
          <div class="user-meta">
            <span class="user-name">{{ auth.user()?.name }}</span>
            <span class="user-email">{{ auth.user()?.email }}</span>
          </div>
          <button (click)="logout()" title="Sign out">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
          </button>
        </div>
      </aside>
      <main class="content">
        <router-outlet></router-outlet>
      </main>
    </div>
  `,
  styles: [`
    :host {
      display: block;
      font-family: var(--font-sans);
    }

    .shell {
      display: flex;
      min-height: 100vh;
      background: var(--color-background);
    }

    .sidebar {
      width: 240px;
      flex: 0 0 240px;
      background: var(--color-primary);
      color: var(--color-on-primary);
      display: flex;
      flex-direction: column;
      padding: var(--space-5) 0 var(--space-4);
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: var(--space-2);
      padding: 0 var(--space-4);
      margin-bottom: var(--space-5);
    }

    .brand-mark {
      width: 30px;
      height: 30px;
      flex: 0 0 30px;
      border-radius: var(--radius-sm);
      background: var(--color-accent);
      color: var(--color-on-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: var(--font-size-base);
    }

    .brand-name {
      font-weight: 600;
      font-size: var(--font-size-lg);
      letter-spacing: -0.01em;
    }

    nav {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: var(--space-4);
      padding: 0 var(--space-3);
    }

    .nav-group {
      display: flex;
      flex-direction: column;
      gap: 1px;
    }

    .nav-group-label {
      font-size: 0.6875rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: rgba(255, 255, 255, 0.45);
      padding: 0 var(--space-2);
      margin-bottom: var(--space-1);
    }

    nav a {
      display: flex;
      align-items: center;
      gap: 10px;
      color: rgba(255, 255, 255, 0.75);
      text-decoration: none;
      padding: 7px var(--space-2);
      border-radius: var(--radius-sm);
      font-size: var(--font-size-sm);
      font-weight: 500;
      border-left: 2px solid transparent;
      transition: background var(--transition-fast), color var(--transition-fast);
    }

    nav a svg {
      width: 16px;
      height: 16px;
      flex: 0 0 16px;
      fill: none;
      stroke: currentColor;
      stroke-width: 1.6;
      stroke-linecap: round;
      stroke-linejoin: round;
      opacity: 0.85;
    }

    nav a:hover {
      background: rgba(255, 255, 255, 0.06);
      color: var(--color-on-primary);
    }

    nav a.active {
      background: rgba(255, 255, 255, 0.1);
      color: var(--color-on-primary);
      border-left-color: var(--color-accent);
    }

    nav a.active svg {
      opacity: 1;
    }

    .user {
      margin-top: var(--space-4);
      padding: var(--space-3) var(--space-4) 0;
      border-top: 1px solid rgba(255, 255, 255, 0.12);
      display: flex;
      align-items: center;
      gap: var(--space-2);
    }

    .user-avatar {
      width: 34px;
      height: 34px;
      flex: 0 0 34px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.12);
      color: var(--color-on-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: var(--font-size-sm);
    }

    .user-meta {
      display: flex;
      flex-direction: column;
      min-width: 0;
      flex: 1;
    }

    .user-name {
      font-size: var(--font-size-sm);
      font-weight: 600;
      color: var(--color-on-primary);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .user-email {
      font-size: 0.6875rem;
      color: rgba(255, 255, 255, 0.5);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .user button {
      flex: 0 0 auto;
      width: 30px;
      height: 30px;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background: transparent;
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: var(--radius-sm);
      color: rgba(255, 255, 255, 0.75);
      cursor: pointer;
      transition: background var(--transition-fast), color var(--transition-fast);
    }

    .user button:hover {
      background: rgba(255, 255, 255, 0.1);
      color: var(--color-on-primary);
    }

    .user button svg {
      width: 15px;
      height: 15px;
      fill: none;
      stroke: currentColor;
      stroke-width: 1.8;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .content {
      flex: 1;
      min-width: 0;
      padding: var(--space-6);
    }

    @media (max-width: 900px) {
      .sidebar {
        width: 72px;
        flex-basis: 72px;
      }
      .brand-name,
      .nav-group-label,
      nav a span,
      .user-meta {
        display: none;
      }
      nav a {
        justify-content: center;
      }
      .content {
        padding: var(--space-4);
      }
    }
  `],
})
export class DashboardComponent {
  constructor(public auth: AuthService, private router: Router) {}

  initial(): string {
    const name = this.auth.user()?.name ?? '';
    return name.trim().charAt(0).toUpperCase() || '?';
  }

  logout(): void {
    this.auth.logout().subscribe({
      next: () => this.router.navigate(['/login']),
      error: () => {
        this.auth.clearSessionLocally();
        this.router.navigate(['/login']);
      },
    });
  }
}
