import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  TerminalLookupResult,
  TerminalLookupService,
  TerminalLookupType,
} from '../../core/terminal-lookup.service';

@Component({
  selector: 'app-terminal-callup',
  standalone: true,
  imports: [FormsModule],
  template: `
    <header class="page-header">
      <div>
        <p class="eyebrow">Terminal management</p>
        <h1>Terminal Call-up</h1>
        <p class="subtitle">Find a terminal using either its terminal ID or device serial number.</p>
      </div>
    </header>

    <section class="lookup-card" aria-labelledby="lookup-heading">
      <div class="card-heading">
        <span class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><rect x="5" y="2.5" width="14" height="19" rx="2.5"/><path d="M8.5 6.5h7M8 10h8v5H8zM9 18h.01M12 18h.01M15 18h.01"/></svg>
        </span>
        <div>
          <h2 id="lookup-heading">Look up a terminal</h2>
          <p>Choose the identifier you have, then enter it below.</p>
        </div>
      </div>

      <form (ngSubmit)="lookup()" #lookupForm="ngForm" novalidate>
        <fieldset>
          <legend>Search using</legend>
          <div class="lookup-toggle">
            <label [class.active]="lookupType === 'terminal-id'">
              <input type="radio" name="lookupType" value="terminal-id" [(ngModel)]="lookupType" (ngModelChange)="changeLookupType()" />
              Terminal ID
            </label>
            <label [class.active]="lookupType === 'serial-number'">
              <input type="radio" name="lookupType" value="serial-number" [(ngModel)]="lookupType" (ngModelChange)="changeLookupType()" />
              Serial Number
            </label>
          </div>
        </fieldset>

        <label class="input-label" for="terminal-identifier">{{ inputLabel }}</label>
        <div class="input-row">
          <input
            id="terminal-identifier"
            name="identifier"
            type="text"
            [attr.inputmode]="lookupType === 'terminal-id' ? 'numeric' : 'text'"
            autocomplete="off"
            [placeholder]="lookupType === 'terminal-id' ? 'e.g. 10408482' : 'e.g. 28260815000102'"
            [pattern]="lookupType === 'terminal-id' ? '[0-9]{4,32}' : '[A-Za-z0-9_-]{4,64}'"
            maxlength="64"
            required
            [(ngModel)]="identifier"
            #identifierField="ngModel"
            [attr.aria-describedby]="error() ? 'lookup-error' : 'terminal-hint'"
          />
          <button type="submit" [disabled]="loading() || lookupForm.invalid">
            @if (loading()) {
              <span class="spinner" aria-hidden="true"></span>
              Looking up...
            } @else {
              Search
            }
          </button>
        </div>
        <p id="terminal-hint" class="hint">{{ inputHint }}</p>
        @if (identifierField.invalid && identifierField.touched) {
          <p class="field-error">Enter a valid {{ inputLabel.toLowerCase() }}.</p>
        }
      </form>
    </section>

    @if (error()) {
      <div id="lookup-error" class="alert" role="alert">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg>
        <div><strong>Lookup unsuccessful</strong><span>{{ error() }}</span></div>
      </div>
    }

    @if (result(); as terminal) {
      <section class="result-card" aria-live="polite">
        <div class="result-heading">
          <div>
            <p class="eyebrow">Lookup result</p>
            <h2>Terminal details</h2>
          </div>
          <span class="status" [class.success]="terminal.status.toLowerCase() === 'success'">
            <span class="status-dot"></span>{{ terminal.status }}
          </span>
        </div>

        <dl>
          @if (terminal.terminalid) {
            <div>
            <dt>Terminal ID</dt>
            <dd>{{ terminal.terminalid }}</dd>
            </div>
          }
          @if (terminal.serialnumber) {
            <div>
              <dt>Serial Number</dt>
              <dd>{{ terminal.serialnumber }}</dd>
            </div>
          }
          <div>
            <dt>Manufacturer</dt>
            <dd>{{ terminal.manufacturer }}</dd>
          </div>
        </dl>
      </section>
    }
  `,
  styles: [`
    :host { display: block; max-width: 880px; }
    .page-header { margin-bottom: var(--space-5); }
    .eyebrow { margin: 0 0 var(--space-1); color: var(--color-accent); font-size: var(--font-size-xs); font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
    h1 { margin: 0; font-size: var(--font-size-2xl); letter-spacing: -.025em; }
    .subtitle { margin: var(--space-2) 0 0; color: var(--color-muted); }
    .lookup-card, .result-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: var(--space-5); }
    .card-heading { display: flex; align-items: center; gap: var(--space-3); padding-bottom: var(--space-4); margin-bottom: var(--space-4); border-bottom: 1px solid var(--color-border); }
    .icon { width: 42px; height: 42px; flex: 0 0 42px; display: grid; place-items: center; border-radius: var(--radius-md); background: var(--color-muted-bg); color: var(--color-secondary); }
    .icon svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
    h2 { margin: 0; font-size: var(--font-size-lg); }
    .card-heading p { margin: 3px 0 0; color: var(--color-muted); font-size: var(--font-size-sm); }
    fieldset { margin: 0 0 var(--space-4); padding: 0; border: 0; }
    legend, .input-label { display: block; margin-bottom: var(--space-2); font-size: var(--font-size-sm); font-weight: 600; }
    .lookup-toggle { width: fit-content; display: flex; padding: 3px; gap: 2px; background: var(--color-muted-bg); border-radius: var(--radius-md); }
    .lookup-toggle label { position: relative; margin: 0; padding: 7px var(--space-3); border-radius: var(--radius-sm); color: var(--color-muted); cursor: pointer; font-size: var(--font-size-sm); font-weight: 600; transition: background var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast); }
    .lookup-toggle label.active { color: var(--color-foreground); background: var(--color-surface); box-shadow: var(--shadow-sm); }
    .lookup-toggle input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .lookup-toggle label:has(input:focus-visible) { outline: 2px solid var(--color-ring); outline-offset: 2px; }
    .input-row { display: flex; gap: var(--space-3); }
    input { flex: 1; min-width: 0; height: 44px; padding: 0 var(--space-3); color: var(--color-foreground); background: var(--color-surface); border: 1px solid #cbd5e1; border-radius: var(--radius-sm); font-size: var(--font-size-base); transition: border-color var(--transition-fast), box-shadow var(--transition-fast); }
    input::placeholder { color: #94a3b8; }
    input:focus { border-color: var(--color-secondary); box-shadow: 0 0 0 3px rgba(30, 58, 138, .1); outline: none; }
    button { min-width: 160px; height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: var(--space-2); padding: 0 var(--space-4); border: 0; border-radius: var(--radius-sm); color: var(--color-on-primary); background: var(--color-primary); font-size: var(--font-size-sm); font-weight: 600; transition: background var(--transition-fast), opacity var(--transition-fast); }
    button:hover:not(:disabled) { background: var(--color-primary-hover); }
    button:disabled { cursor: not-allowed; opacity: .55; }
    .spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,.4); border-top-color: white; border-radius: 50%; animation: spin .7s linear infinite; }
    .hint, .field-error { margin: var(--space-2) 0 0; font-size: var(--font-size-xs); color: var(--color-muted); }
    .field-error { color: var(--color-danger); }
    .alert { display: flex; gap: var(--space-3); align-items: flex-start; margin-top: var(--space-4); padding: var(--space-3) var(--space-4); color: var(--color-danger); background: var(--color-danger-bg); border: 1px solid #fecaca; border-radius: var(--radius-md); }
    .alert svg { width: 19px; height: 19px; flex: 0 0 19px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; }
    .alert div { display: flex; flex-direction: column; gap: 2px; }
    .alert strong, .alert span { font-size: var(--font-size-sm); }
    .result-card { margin-top: var(--space-4); }
    .result-heading { display: flex; justify-content: space-between; align-items: center; gap: var(--space-4); padding-bottom: var(--space-4); border-bottom: 1px solid var(--color-border); }
    .status { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border-radius: 999px; color: var(--color-muted); background: var(--color-muted-bg); font-size: var(--font-size-xs); font-weight: 700; text-transform: capitalize; }
    .status.success { color: var(--color-success); background: var(--color-success-bg); }
    .status-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
    dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0; }
    dl div { padding: var(--space-4) 0 0; }
    dt { color: var(--color-muted); font-size: var(--font-size-xs); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
    dd { margin: var(--space-1) 0 0; font-size: var(--font-size-base); font-weight: 600; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @media (max-width: 640px) {
      :host { max-width: 100%; }
      .lookup-card, .result-card { padding: var(--space-4); }
      .input-row { flex-direction: column; }
      button { width: 100%; }
      dl { grid-template-columns: 1fr; }
    }
  `],
})
export class TerminalCallupComponent {
  lookupType: TerminalLookupType = 'serial-number';
  identifier = '';
  loading = signal(false);
  error = signal<string | null>(null);
  result = signal<TerminalLookupResult | null>(null);

  constructor(private terminalLookup: TerminalLookupService) {}

  get inputLabel(): string {
    return this.lookupType === 'terminal-id' ? 'Terminal ID' : 'Serial Number';
  }

  get inputHint(): string {
    return this.lookupType === 'terminal-id'
      ? 'Enter the numeric terminal ID to retrieve its serial number.'
      : 'Enter the device serial number to retrieve its terminal ID.';
  }

  changeLookupType(): void {
    this.identifier = '';
    this.error.set(null);
    this.result.set(null);
  }

  lookup(): void {
    const identifier = this.identifier.trim();
    const isValid = this.lookupType === 'terminal-id'
      ? /^\d{4,32}$/.test(identifier)
      : /^[A-Za-z0-9_-]{4,64}$/.test(identifier);

    if (!isValid) {
      return;
    }

    this.loading.set(true);
    this.error.set(null);
    this.result.set(null);

    this.terminalLookup.lookup(this.lookupType, identifier).subscribe({
      next: (result) => {
        this.result.set(result);
        this.loading.set(false);
      },
      error: (error) => {
        this.error.set(error?.error?.message ?? 'Unable to look up the terminal. Please try again.');
        this.loading.set(false);
      },
    });
  }
}
