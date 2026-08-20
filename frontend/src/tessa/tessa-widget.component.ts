import { CommonModule } from '@angular/common';
import { AfterViewChecked, Component, ElementRef, OnDestroy, OnInit, ViewChild, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { MerchantPortalSessionService } from '../app/core/merchant-portal-session.service';
import { TessaAttachment, TessaMessage } from './tessa.models';
import { TessaService } from './tessa.service';

const SUPPORTED_ATTACHMENT_PATTERN = /\.(pdf|txt)$/i;

@Component({
  selector: 'app-tessa-widget',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <aside class="tessa" [class.open]="open()">
      @if (open()) {
        <section class="chat" role="dialog" aria-label="Chat with Tessa">
          <header><div class="identity"><div class="avatar" [class.thinking]="typing()"><img src="/tessa/tessa-avatar.png" alt="Tessa" /><i></i></div><div><strong>Tessa</strong><span>MicroBiz assistant · Online</span></div></div><div class="header-actions"><button type="button" title="Start a new conversation" (click)="clear()">↻</button><button type="button" title="Minimize Tessa" (click)="open.set(false)">−</button></div></header>
          <div class="messages" #messageList aria-live="polite">
            <div class="day">Today</div>
            @for (message of messages(); track message.id) {
              <div class="message-row" [class.user]="message.role === 'user'">
                @if (message.role === 'assistant') { <img src="/tessa/tessa-avatar.png" alt="" /> }
                <div class="bubble">
                  @if (message.content) { <p>{{ message.content }}</p> }
                  @if (message.attachments?.length) {
                    <div class="msg-attachments">
                      @for (attachment of message.attachments; track attachment.name) {
                        <div class="msg-attachment">
                          @if (attachment.previewUrl) { <img [src]="attachment.previewUrl" alt="" /> } @else { <span class="file-icon">📄</span> }
                          <span>{{ attachment.name }}</span>
                        </div>
                      }
                    </div>
                  }
                  <time>{{ message.createdAt | date:'HH:mm' }}</time>
                </div>
              </div>
            }
            @if (typing()) { <div class="message-row"><img src="/tessa/tessa-avatar.png" alt="" /><div class="typing"><i></i><i></i><i></i></div></div> }
          </div>
          @if (attachments().length) {
            <div class="attachments">
              @for (attachment of attachments(); track attachment.name; let i = $index) {
                <div class="chip">
                  @if (attachment.previewUrl) { <img [src]="attachment.previewUrl" alt="" /> } @else { <span class="file-icon">📄</span> }
                  <span>{{ attachment.name }}</span>
                  <button type="button" (click)="removeAttachment(i)" aria-label="Remove attachment">×</button>
                </div>
              }
            </div>
          }
          @if (error()) { <div class="chat-error">{{ error() }}</div> }
          <form (ngSubmit)="send()">
            <input #fileInput type="file" hidden multiple accept="image/*,.pdf,.txt,text/plain,application/pdf" (change)="onFilesSelected($event)" />
            <button type="button" class="attach-btn" title="Attach an image, text, or PDF file" (click)="fileInput.click()" aria-label="Attach a file">📎</button>
            <textarea name="draft" [(ngModel)]="draft" rows="1" maxlength="500" placeholder="Ask Tessa anything..." aria-label="Message Tessa" (keydown.enter)="onEnter($event)"></textarea>
            <button type="submit" [disabled]="(!draft.trim() && !attachments().length) || typing()" aria-label="Send message">➤</button>
          </form>
          <footer>Tessa can make mistakes. Confirm financial details in your records.</footer>
        </section>
      }
      @if (teaser() && !open()) {
        <div class="teaser" role="status" (click)="openFromTeaser()">
          <button type="button" class="teaser-close" (click)="dismissTeaser($event)" aria-label="Dismiss">×</button>
          <p>{{ teaser() }}</p>
        </div>
      }
      <button class="launcher" [class.idle]="!open()" type="button" (click)="toggle()" [attr.aria-expanded]="open()" aria-label="Open Tessa assistant"><img src="/tessa/tessa-avatar.png" alt="" /><span><strong>Ask Tessa</strong><small>Your MicroBiz assistant</small></span>@if (!open() && unread()) { <i></i> }</button>
    </aside>
  `,
  styles: [`
    :host{position:fixed;right:clamp(1rem,2.5vw,2rem);bottom:clamp(1rem,2.5vw,2rem);z-index:80;font-family:inherit}.tessa{display:grid;justify-items:end;gap:.75rem}.chat{width:min(390px,calc(100vw - 2rem));height:min(600px,calc(100vh - 7rem));display:grid;grid-template-rows:auto 1fr auto auto auto auto;background:#fff;border:1px solid #dbe0ec;border-radius:1rem;overflow:hidden;box-shadow:0 24px 70px rgba(20,29,71,.22);animation:appear .18s ease-out}.chat>header{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1rem;background:linear-gradient(120deg,#172052,#273b84);color:white}.identity{display:flex;align-items:center;gap:.65rem}.avatar{position:relative;width:2.7rem;height:2.7rem;border-radius:50%;overflow:hidden;background:#fff;border:2px solid rgba(255,255,255,.7)}.avatar img{width:100%;height:100%;object-fit:cover;object-position:50% 18%}.avatar i{position:absolute;right:1px;bottom:2px;width:.55rem;height:.55rem;border-radius:50%;background:#43d48d;border:2px solid white}.avatar.thinking{animation:tessaDance .9s ease-in-out infinite}.identity>div:last-child{display:grid;gap:.15rem}.identity strong{font-size:1.05rem}.identity span{font-size:.72rem;color:#c4ccec}.header-actions{display:flex;gap:.25rem}.header-actions button{width:1.9rem;height:1.9rem;border:0;border-radius:50%;background:rgba(255,255,255,.1);color:#fff;font-size:1.1rem}.messages{overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:.8rem;background:#f7f8fb}.day{align-self:center;color:#979dad;font-size:.68rem;text-transform:uppercase;letter-spacing:.08em}.message-row{display:flex;align-items:end;gap:.5rem;max-width:90%}.message-row.user{align-self:flex-end;justify-content:flex-end}.message-row>img{width:1.9rem;height:1.9rem;flex:0 0 auto;border-radius:50%;object-fit:cover;object-position:50% 18%;background:white}.bubble{padding:.85rem 1rem;border-radius:1rem 1rem 1rem .25rem;background:white;border:1px solid #e1e5ed;box-shadow:0 3px 10px rgba(30,39,97,.04)}.user .bubble{border:0;border-radius:1rem 1rem .25rem 1rem;background:#253875;color:white}.bubble p{margin:0;font-size:1rem;line-height:1.6;white-space:pre-line}.bubble time{display:block;margin-top:.35rem;text-align:right;color:#9ba1b1;font-size:.66rem}.user .bubble time{color:#aeb9df}.msg-attachments{display:flex;flex-direction:column;gap:.35rem;margin:.35rem 0}.msg-attachment{display:flex;align-items:center;gap:.45rem;font-size:.72rem}.msg-attachment img{width:2.8rem;height:2.8rem;border-radius:.5rem;object-fit:cover}.file-icon{font-size:1.15rem}.typing{display:flex;align-items:flex-end;gap:.26rem;padding:.7rem .85rem;border:1px solid #e1e5ed;border-radius:.8rem .8rem .8rem .2rem;background:white;height:2.2rem}.typing i{width:.32rem;height:.5rem;border-radius:.2rem;background:linear-gradient(180deg,#6376bb,#ffca42);animation:tessaEqualize .9s ease-in-out infinite}.typing i:nth-child(2){animation-delay:.15s}.typing i:nth-child(3){animation-delay:.3s}.attachments{display:flex;gap:.4rem;padding:.6rem .75rem 0;flex-wrap:wrap;background:white}.chip{display:flex;align-items:center;gap:.35rem;padding:.3rem .5rem;border:1px solid #d6ddef;border-radius:.6rem;background:#f8f9fd;font-size:.68rem;color:#344b89;max-width:160px}.chip img{width:1.35rem;height:1.35rem;border-radius:.3rem;object-fit:cover}.chip span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.chip button{border:0;background:transparent;color:#8a90a6;font-size:.9rem;line-height:1;cursor:pointer;padding:0 0 0 .1rem}.chat-error{padding:.45rem .8rem;background:#fff0ee;color:#a43b2b;font-size:.72rem}.chat form{display:grid;grid-template-columns:auto 1fr auto;align-items:end;gap:.5rem;padding:.7rem .75rem;border-top:1px solid #e6e9f0;background:white}.attach-btn{width:2.25rem;height:2.25rem;border:1px solid #d9deea;border-radius:.65rem;background:#f8f9fd;color:#344b89;font-size:1rem;display:flex;align-items:center;justify-content:center}.chat textarea{resize:none;max-height:90px;border:1px solid #d9deea;border-radius:.7rem;padding:.65rem .7rem;font:inherit;font-size:.88rem;line-height:1.4;outline:none}.chat textarea:focus{border-color:#6376bb;box-shadow:0 0 0 3px rgba(59,79,155,.1)}.chat form button[type=submit]{width:2.25rem;height:2.25rem;border:0;border-radius:.65rem;background:#ffca42;color:#172052;font-weight:900}.chat form button:disabled{opacity:.45}.chat footer{text-align:center;padding:0 .7rem .55rem;color:#9a9fae;background:white;font-size:.62rem}.launcher{position:relative;display:flex;align-items:center;gap:.65rem;min-width:185px;padding:.5rem .85rem .5rem .5rem;border:0;border-radius:2rem;background:#172052;color:white;box-shadow:0 12px 32px rgba(23,32,82,.28);text-align:left}.launcher img{width:2.65rem;height:2.65rem;border-radius:50%;object-fit:cover;object-position:50% 18%;background:white;border:2px solid white}.launcher span{display:grid;gap:.1rem}.launcher strong{font-size:.88rem}.launcher small{color:#b8c1e2;font-size:.66rem}.launcher>i{position:absolute;right:0;top:0;width:.72rem;height:.72rem;border-radius:50%;background:#ffca42;border:2px solid white}.launcher.idle{animation:tessaFloat 3s ease-in-out infinite,tessaGlow 2.4s ease-in-out infinite}.teaser{position:relative;max-width:230px;padding:.75rem 1.5rem .75rem .95rem;background:#fff;border:1px solid #dbe0ec;border-radius:1rem;box-shadow:0 14px 34px rgba(20,29,71,.2);cursor:pointer;animation:teaserPop .35s ease-out,tessaFloat 3s ease-in-out infinite}.teaser p{margin:0;font-size:.78rem;line-height:1.45;color:#1c2440}.teaser-close{position:absolute;top:.4rem;right:.4rem;width:1.3rem;height:1.3rem;border:0;border-radius:50%;background:#f1f3f9;color:#7d84a0;font-size:.75rem;line-height:1;display:flex;align-items:center;justify-content:center}.teaser::after{content:'';position:absolute;bottom:-.4rem;right:1.9rem;width:.8rem;height:.8rem;background:#fff;border-right:1px solid #dbe0ec;border-bottom:1px solid #dbe0ec;transform:rotate(45deg)}@keyframes appear{from{opacity:0;transform:translateY(10px) scale(.98)}}@keyframes teaserPop{from{opacity:0;transform:translateY(8px) scale(.92)}}@keyframes tessaEqualize{0%,100%{height:.5rem}50%{height:1.1rem}}@keyframes tessaFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}@keyframes tessaGlow{0%,100%{box-shadow:0 12px 32px rgba(23,32,82,.28),0 0 0 0 rgba(255,202,66,.45)}50%{box-shadow:0 16px 38px rgba(23,32,82,.32),0 0 0 9px rgba(255,202,66,0)}}@keyframes tessaDance{0%,100%{transform:rotate(0) scale(1)}25%{transform:rotate(-6deg) scale(1.05)}50%{transform:rotate(0) scale(1.1)}75%{transform:rotate(6deg) scale(1.05)}}
    @media(max-width:520px){:host{right:max(.65rem,env(safe-area-inset-right));bottom:max(.65rem,env(safe-area-inset-bottom));left:max(.65rem,env(safe-area-inset-left))}.tessa{width:100%}.chat{width:100%;height:calc(100dvh - 5.5rem);max-height:none}.messages{padding:.75rem}.message-row{max-width:94%}.launcher{min-width:0}.launcher span{display:none}}
    @media(prefers-reduced-motion:reduce){.chat,.typing i,.launcher.idle,.avatar.thinking,.teaser{animation:none}}
  `],
})
export class TessaWidgetComponent implements AfterViewChecked, OnInit, OnDestroy {
  @ViewChild('messageList') private messageList?: ElementRef<HTMLElement>;
  open = signal(false); unread = signal(true); typing = signal(false); error = signal<string | null>(null); draft = ''; private shouldScroll = false; private introShown = false;
  messages = signal<TessaMessage[]>([]);
  attachments = signal<TessaAttachment[]>([]);
  teaser = signal<string | null>(null);
  private readonly teaserMessages = [
    "Hi, I'm Tessa 👋 How can I help you today?",
    'Need a hand getting set up? Just ask!',
    'Stuck on a step? Tap here and ask me anything.',
    'I can explain settlements, disputes, and reports.',
  ];
  private teaserCycleIndex = 0;
  private teaserTimer?: ReturnType<typeof setTimeout>;

  constructor(private readonly tessa: TessaService, private readonly session: MerchantPortalSessionService, private readonly router: Router) { this.messages.set(this.restore()); if (this.messages().length > 0) this.introShown = true; }
  ngOnInit(): void { this.scheduleTeaser(2000); }
  ngOnDestroy(): void { if (this.teaserTimer) clearTimeout(this.teaserTimer); }
  ngAfterViewChecked(): void { if (this.shouldScroll && this.messageList) { this.messageList.nativeElement.scrollTop = this.messageList.nativeElement.scrollHeight; this.shouldScroll = false; } }
  toggle(): void {
    this.open.update((value) => !value);
    if (this.open()) {
      this.unread.set(false); this.teaser.set(null); this.queueScroll();
      if (!this.introShown && this.messages().length === 0) { this.introShown = true; this.playIntro(); }
    }
  }
  private playIntro(): void {
    this.typing.set(true);
    this.queueScroll();
    setTimeout(() => { this.typing.set(false); this.messages.set([this.welcome()]); this.persist(); this.queueScroll(); }, 1200);
  }
  openFromTeaser(): void { this.teaser.set(null); this.open.set(true); this.unread.set(false); this.queueScroll(); }
  dismissTeaser(event: Event): void { event.stopPropagation(); this.teaser.set(null); if (this.teaserTimer) clearTimeout(this.teaserTimer); this.scheduleTeaser(30000); }
  private scheduleTeaser(delay: number): void {
    this.teaserTimer = setTimeout(() => {
      if (this.open()) { this.scheduleTeaser(25000); return; }
      this.teaser.set(this.teaserMessages[this.teaserCycleIndex % this.teaserMessages.length]);
      this.teaserCycleIndex++;
      this.teaserTimer = setTimeout(() => { this.teaser.set(null); this.scheduleTeaser(25000); }, 7000);
    }, delay);
  }
  onEnter(event: Event): void { const keyboard = event as KeyboardEvent; if (!keyboard.shiftKey) { keyboard.preventDefault(); this.send(); } }
  onFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []).filter((file) => this.isSupportedFile(file));
    const additions = files.map((file): TessaAttachment => ({ name: file.name, type: file.type, size: file.size, previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : undefined }));
    this.attachments.update((items) => [...items, ...additions]);
    input.value = '';
  }
  removeAttachment(index: number): void {
    const removed = this.attachments()[index];
    if (removed?.previewUrl) URL.revokeObjectURL(removed.previewUrl);
    this.attachments.update((items) => items.filter((_, i) => i !== index));
  }
  send(): void {
    const content = this.draft.trim();
    const attachments = this.attachments();
    if ((!content && !attachments.length) || this.typing()) return;
    this.draft = ''; this.attachments.set([]); this.error.set(null);
    this.append('user', content, attachments);
    this.typing.set(true);
    const active = this.session.session();
    this.tessa.send(content, { route: this.router.url, businessName: active?.businessName ?? 'your business', accountNumber: active?.accountNumber ?? 'your merchant account' }, attachments).subscribe({ next: (reply) => { this.typing.set(false); this.append('assistant', reply); }, error: (error) => { this.typing.set(false); this.error.set(error?.error?.message ?? 'Tessa could not respond. Please try again.'); } });
  }
  clear(): void { this.messages.set([]); this.error.set(null); this.introShown = true; this.playIntro(); }
  private isSupportedFile(file: File): boolean { return file.type.startsWith('image/') || file.type === 'application/pdf' || file.type === 'text/plain' || SUPPORTED_ATTACHMENT_PATTERN.test(file.name); }
  private append(role: TessaMessage['role'], content: string, attachments: TessaAttachment[] = []): void { this.messages.update((items) => [...items, { id: `${role}-${Date.now()}-${items.length}`, role, content, createdAt: new Date().toISOString(), ...(attachments.length ? { attachments } : {}) }]); this.persist(); this.queueScroll(); }
  private welcome(): TessaMessage { const name = this.session.session()?.businessName; return { id: `welcome-${Date.now()}`, role: 'assistant', content: `Hello${name ? `, ${name}` : ''}! I’m Tessa, your MicroBiz assistant. How can I help today?`, createdAt: new Date().toISOString() }; }
  private restore(): TessaMessage[] { try { const stored = sessionStorage.getItem(this.storageKey()); if (stored) return JSON.parse(stored) as TessaMessage[]; } catch { /* Start a fresh safe conversation. */ } return []; }
  private persist(): void { try { sessionStorage.setItem(this.storageKey(), JSON.stringify(this.messages().slice(-30).map(({ attachments, ...rest }) => rest))); } catch { /* Chat still works when browser storage is unavailable. */ } }
  private storageKey(): string { return `microbiz_tessa_messages_${this.session.session()?.merchantId ?? 'preview'}`; }
  private queueScroll(): void { this.shouldScroll = true; }
}
