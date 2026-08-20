import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { TessaWidgetComponent } from '../tessa/tessa-widget.component';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, TessaWidgetComponent],
  template: `<router-outlet></router-outlet><app-tessa-widget />`,
})
export class App {}
