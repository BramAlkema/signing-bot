# Signing Bot - Product Mission

## Vision

A unified multi-messenger signing bot that enables Nextcloud users to request and complete document signatures from any chat platform they prefer.

## Problem Statement

Document signing workflows are fragmented. Users receive signing requests via email, but communicate daily through various messengers (Matrix, Signal, Telegram, Discord, Slack). This creates friction:

- Signing requests get lost in email
- No visibility into signing status from chat
- Manual back-and-forth to share documents and collect signatures

## Solution

Signing Bot bridges the gap between chat platforms and document signing. Users can:

- Send documents for signature directly from chat (`/sign`)
- Check signing status without leaving their messenger (`/status`)
- Browse available templates (`/templates`)
- Receive notifications when documents are signed

## Target Users

**Primary**: Nextcloud users who need document signing integrated into their self-hosted ecosystem.

**Use Cases**:
- Small businesses sending contracts to clients
- Teams collecting internal approvals
- Freelancers getting project sign-offs
- Organizations with compliance/audit requirements

## Core Principles

1. **Self-hosted first** - Runs alongside Nextcloud and DocuSeal on user infrastructure
2. **Messenger-agnostic** - Same commands work across Matrix, Signal, Telegram, Discord, Slack
3. **DocuSeal as bridge** - DocuSeal handles the actual signing workflow; we handle the chat interface
4. **Simple commands** - `/sign`, `/status`, `/templates` - that's it

## Success Metrics

- Documents signed via chat vs email
- Time from signature request to completion
- Number of active messenger integrations
- User adoption across different platforms
