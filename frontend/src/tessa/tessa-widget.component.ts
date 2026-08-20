import { CommonModule } from '@angular/common';
import { AfterViewChecked, Component, ElementRef, ViewChild, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { MerchantPortalSessionService } from '../app/core/merchant-portal-session.service';
import { TessaMessage } from './tessa.models';
import { TessaService } from './tessa.service';

@Component({
  selector: 'app-tessa-widget',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <aside class="tessa" [class.open]="open()">
      @if (open()) {
        <section class="chat" role="dialog" aria-label="Chat with Tessa">
          <header><div class="identity"><div class="avatar"><img src="/tessa/tessa-avatar.png" alt="Tessa" /><i></i></div><div><strong>Tessa</strong><span>MicroBiz assistant · Online</span></div></div><div class="header-actions"><button type="button" title="Start a new conversation" (click)="clear()">↻</button><button type="button" title="Minimize Tessa" (click)="open.set(false)">−</button></div></header>
          <div class="messages" #messageList aria-live="polite">
            <div class="day">Today</div>
            @for (message of messages(); track message.id) { <div class="message-row" [class.user]="message.role === 'user'">@if (message.role === 'assistant') { <img src="/tessa/tessa-avatar.png" alt="" /> }<div class="bubble"><p>{{ message.content }}</p><time>{{ message.createdAt | date:'HH:mm' }}</time></div></div> }
            @if (typing()) { <div class="message-row"><img src="/tessa/tessa-avatar.png" alt="" /><div class="typing"><i></i><i></i><i></i></div></div> }
          </div>
          @if (messages().length < 3) { <div class="suggestions">@for (suggestion of suggestions; track suggestion) { <button type="button" (click)="ask(suggestion)">{{ suggestion }}</button> }</div> }
          @if (error()) { <div class="chat-error">{{ error() }}</div> }
          <form (ngSubmit)="send()"><textarea name="draft" [(ngModel)]="draft" rows="1" maxlength="500" placeholder="Ask Tessa anything..." aria-label="Message Tessa" (keydown.enter)="onEnter($event)"></textarea><button type="submit" [disabled]="!draft.trim() || typing()" aria-label="Send message">➤</button></form>
          <footer>Tessa can make mistakes. Confirm financial details in your records.</footer>
        </section>
      }
      <button class="launcher" type="button" (click)="toggle()" [attr.aria-expanded]="open()" aria-label="Open Tessa assistant"><img src="/tessa/tessa-avatar.png" alt="" /><span><strong>Ask Tessa</strong><small>Your MicroBiz assistant</small></span>@if (!open() && unread()) { <i></i> }</button>
    </aside>
  `,
  styles: [`
    :host{position:fixed;right:clamp(1rem,2.5vw,2rem);bottom:clamp(1rem,2.5vw,2rem);z-index:80;font-family:inherit}.tessa{display:grid;justify-items:end;gap:.75rem}.chat{width:min(390px,calc(100vw - 2rem));height:min(600px,calc(100vh - 7rem));display:grid;grid-template-rows:auto 1fr auto auto auto;background:#fff;border:1px solid #dbe0ec;border-radius:1rem;overflow:hidden;box-shadow:0 24px 70px rgba(20,29,71,.22);animation:appear .18s ease-out}.chat>header{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1rem;background:linear-gradient(120deg,#172052,#273b84);color:white}.identity{display:flex;align-items:center;gap:.65rem}.avatar{position:relative;width:2.7rem;height:2.7rem;border-radius:50%;overflow:hidden;background:#fff;border:2px solid rgba(255,255,255,.7)}.avatar img{width:100%;height:100%;object-fit:cover;object-position:50% 18%}.avatar i{position:absolute;right:1px;bottom:2px;width:.55rem;height:.55rem;border-radius:50%;background:#43d48d;border:2px solid white}.identity>div:last-child{display:grid;gap:.15rem}.identity strong{font-size:.88rem}.identity span{font-size:.6rem;color:#c4ccec}.header-actions{display:flex;gap:.25rem}.header-actions button{width:1.9rem;height:1.9rem;border:0;border-radius:50%;background:rgba(255,255,255,.1);color:#fff;font-size:1rem}.messages{overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:.8rem;background:#f7f8fb}.day{align-self:center;color:#979dad;font-size:.57rem;text-transform:uppercase;letter-spacing:.08em}.message-row{display:flex;align-items:end;gap:.42rem;max-width:88%}.message-row.user{align-self:flex-end;justify-content:flex-end}.message-row>img{width:1.65rem;height:1.65rem;flex:0 0 auto;border-radius:50%;object-fit:cover;object-position:50% 18%;background:white}.bubble{padding:.68rem .75rem;border-radius:.8rem .8rem .8rem .2rem;background:white;border:1px solid #e1e5ed;box-shadow:0 3px 10px rgba(30,39,97,.04)}.user .bubble{border:0;border-radius:.8rem .8rem .2rem .8rem;background:#253875;color:white}.bubble p{margin:0;font-size:.7rem;line-height:1.55;white-space:pre-line}.bubble time{display:block;margin-top:.3rem;text-align:right;color:#9ba1b1;font-size:.52rem}.user .bubble time{color:#aeb9df}.typing{display:flex;gap:.24rem;padding:.65rem .8rem;border:1px solid #e1e5ed;border-radius:.8rem .8rem .8rem .2rem;background:white}.typing i{width:.35rem;height:.35rem;border-radius:50%;background:#9ba3b8;animation:bounce 1s infinite}.typing i:nth-child(2){animation-delay:.15s}.typing i:nth-child(3){animation-delay:.3s}.suggestions{display:flex;gap:.4rem;padding:.6rem .75rem;overflow-x:auto;border-top:1px solid #e8ebf1;background:white}.suggestions button{white-space:nowrap;border:1px solid #d6ddef;border-radius:1rem;background:#f8f9fd;color:#344b89;padding:.42rem .58rem;font-size:.58rem;font-weight:700}.chat-error{padding:.45rem .8rem;background:#fff0ee;color:#a43b2b;font-size:.6rem}.chat form{display:grid;grid-template-columns:1fr auto;align-items:end;gap:.5rem;padding:.7rem .75rem;border-top:1px solid #e6e9f0;background:white}.chat textarea{resize:none;max-height:90px;border:1px solid #d9deea;border-radius:.7rem;padding:.65rem .7rem;font:inherit;font-size:.7rem;line-height:1.4;outline:none}.chat textarea:focus{border-color:#6376bb;box-shadow:0 0 0 3px rgba(59,79,155,.1)}.chat form button{width:2.25rem;height:2.25rem;border:0;border-radius:.65rem;background:#ffca42;color:#172052;font-weight:900}.chat form button:disabled{opacity:.45}.chat footer{text-align:center;padding:0 .7rem .55rem;color:#9a9fae;background:white;font-size:.5rem}.launcher{position:relative;display:flex;align-items:center;gap:.65rem;min-width:185px;padding:.5rem .85rem .5rem .5rem;border:0;border-radius:2rem;background:#172052;color:white;box-shadow:0 12px 32px rgba(23,32,82,.28);text-align:left}.launcher img{width:2.65rem;height:2.65rem;border-radius:50%;object-fit:cover;object-position:50% 18%;background:white;border:2px solid white}.launcher span{display:grid;gap:.1rem}.launcher strong{font-size:.73rem}.launcher small{color:#b8c1e2;font-size:.55rem}.launcher>i{position:absolute;right:0;top:0;width:.72rem;height:.72rem;border-radius:50%;background:#ffca42;border:2px solid white}@keyframes appear{from{opacity:0;transform:translateY(10px) scale(.98)}}@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-3px)}}
    @media(max-width:520px){:host{right:max(.65rem,env(safe-area-inset-right));bottom:max(.65rem,env(safe-area-inset-bottom));left:max(.65rem,env(safe-area-inset-left))}.tessa{width:100%}.chat{width:100%;height:calc(100dvh - 5.5rem);max-height:none}.messages{padding:.75rem}.message-row{max-width:94%}.launcher{min-width:0}.launcher span{display:none}}
    @media(prefers-reduced-motion:reduce){.chat,.typing i{animation:none}}
  `],
})
export class TessaWidgetComponent implements AfterViewChecked {
  @ViewChild('messageList') private messageList?: ElementRef<HTMLElement>;
  open = signal(false); unread = signal(true); typing = signal(false); error = signal<string | null>(null); draft = ''; private shouldScroll = false;
  readonly suggestions = ['Show my balance', 'Explain settlements', 'Raise a dispute', 'Download a statement'];
  messages = signal<TessaMessage[]>([]);

  constructor(private readonly tessa: TessaService, private readonly session: MerchantPortalSessionService, private readonly router: Router) { this.messages.set(this.restore()); }
  ngAfterViewChecked(): void { if (this.shouldScroll && this.messageList) { this.messageList.nativeElement.scrollTop = this.messageList.nativeElement.scrollHeight; this.shouldScroll = false; } }
  toggle(): void { this.open.update((value) => !value); if (this.open()) { this.unread.set(false); this.queueScroll(); } }
  ask(suggestion: string): void { this.draft = suggestion; this.send(); }
  onEnter(event: Event): void { const keyboard = event as KeyboardEvent; if (!keyboard.shiftKey) { keyboard.preventDefault(); this.send(); } }
  send(): void { const content = this.draft.trim(); if (!content || this.typing()) return; this.draft = ''; this.error.set(null); this.append('user', content); this.typing.set(true); const active = this.session.session(); this.tessa.send(content, { route: this.router.url, businessName: active?.businessName ?? 'your business', accountNumber: active?.accountNumber ?? 'your merchant account' }).subscribe({ next: (reply) => { this.typing.set(false); this.append('assistant', reply); }, error: (error) => { this.typing.set(false); this.error.set(error?.error?.message ?? 'Tessa could not respond. Please try again.'); } }); }
  clear(): void { this.messages.set([this.welcome()]); this.persist(); this.error.set(null); this.queueScroll(); }
  private append(role: TessaMessage['role'], content: string): void { this.messages.update((items) => [...items, { id: `${role}-${Date.now()}-${items.length}`, role, content, createdAt: new Date().toISOString() }]); this.persist(); this.queueScroll(); }
  private welcome(): TessaMessage { const name = this.session.session()?.businessName; return { id: `welcome-${Date.now()}`, role: 'assistant', content: `Hello${name ? `, ${name}` : ''}! I’m Tessa, your MicroBiz assistant. How can I help today?`, createdAt: new Date().toISOString() }; }
  private restore(): TessaMessage[] { try { const stored = sessionStorage.getItem(this.storageKey()); if (stored) return JSON.parse(stored) as TessaMessage[]; } catch { /* Start a fresh safe conversation. */ } return [this.welcome()]; }
  private persist(): void { try { sessionStorage.setItem(this.storageKey(), JSON.stringify(this.messages().slice(-30))); } catch { /* Chat still works when browser storage is unavailable. */ } }
  private storageKey(): string { return `microbiz_tessa_messages_${this.session.session()?.merchantId ?? 'preview'}`; }
  private queueScroll(): void { this.shouldScroll = true; }
}
