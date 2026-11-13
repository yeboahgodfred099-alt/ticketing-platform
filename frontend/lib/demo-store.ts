import { EventItem, TicketType } from "@/types/event";

export interface DemoEvent extends EventItem {
  ticket_types?: TicketType[];
}

function createTicket(data: Partial<TicketType> & { id: string; event_id: string; name: string; price_minor: number; quantity_total: number }): TicketType {
  const priceMinor = data.price_minor;
  const priceMajor = (priceMinor / 100).toFixed(2);
  const quantityTotal = data.quantity_total;
  const quantitySold = data.quantity_sold ?? 0;
  const quantityReserved = data.quantity_reserved ?? 0;
  const available = Math.max(0, quantityTotal - quantitySold - quantityReserved);

  return {
    id: data.id,
    event_id: data.event_id,
    name: data.name,
    description: data.description ?? null,
    price_minor: priceMinor,
    price_major: priceMajor,
    currency: data.currency ?? "GHS",
    quantity_total: quantityTotal,
    quantity_sold: quantitySold,
    quantity_reserved: quantityReserved,
    available_quantity: available,
    min_per_order: data.min_per_order ?? 1,
    max_per_order: data.max_per_order ?? 10,
    sales_starts_at: data.sales_starts_at ?? null,
    sales_ends_at: data.sales_ends_at ?? null,
    is_within_sale_window: true,
    is_sold_out: available <= 0,
    status: data.status ?? "active",
  };
}

class EventStore {
  private events: DemoEvent[] = [
    {
      id: "e1a90c00-2026-4000-8000-000000000001",
      name: "Accra Music & Culture Summit 2026",
      slug: "accra-music-summit-2026",
      description: "The premier gathering of music innovators, artists, and live performances at Black Star Square.\n\nExperience two stages of non-stop live music, cultural showcases, art installations, and premier Ghanaian cuisine.",
      venue_name: "Accra International Conference Centre",
      venue_address: "Castle Road, Osu, Accra, Ghana",
      banner_url: "https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200",
      starts_at: "2026-12-20T18:00:00Z",
      ends_at: "2026-12-21T02:00:00Z",
      sales_starts_at: "2026-11-01T00:00:00Z",
      sales_ends_at: "2026-12-20T17:00:00Z",
      timezone: "Africa/Accra",
      currency: "GHS",
      status: "published",
      is_purchasable: true,
      is_sold_out: false,
      total_capacity: 750,
      total_sold: 450,
      ticket_types: [
        createTicket({
          id: "t1-early-bird",
          event_id: "e1a90c00-2026-4000-8000-000000000001",
          name: "Early Bird Admission",
          description: "Discounted general admission with access to all main stages and festival grounds.",
          price_minor: 5000,
          quantity_total: 200,
          quantity_sold: 152,
        }),
        createTicket({
          id: "t2-standard",
          event_id: "e1a90c00-2026-4000-8000-000000000001",
          name: "Standard Admission",
          description: "Full access admission to summit sessions, daylight exhibitions, and evening concert.",
          price_minor: 10000,
          quantity_total: 500,
          quantity_sold: 263,
          quantity_reserved: 18,
        }),
        createTicket({
          id: "t3-vip",
          event_id: "e1a90c00-2026-4000-8000-000000000001",
          name: "VIP Golden Circle",
          description: "Premium lounge seating, welcome drinks, priority gate entrance, and artist meet & greet lounge.",
          price_minor: 35000,
          quantity_total: 50,
          quantity_sold: 35,
          quantity_reserved: 6,
        }),
      ],
    },
    {
      id: "e2b90c00-2026-4000-8000-000000000002",
      name: "Ghana Tech Founders Expo 2026",
      slug: "ghana-tech-founders-expo-2026",
      description: "Connect with West Africa's leading technology startups, venture investors, and product innovators.",
      venue_name: "Grand Arena, Accra",
      venue_address: "Independence Avenue, Accra, Ghana",
      banner_url: "https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1200",
      starts_at: "2026-12-28T09:00:00Z",
      ends_at: "2026-12-28T18:00:00Z",
      sales_starts_at: "2026-11-01T00:00:00Z",
      sales_ends_at: "2026-12-28T08:00:00Z",
      timezone: "Africa/Accra",
      currency: "GHS",
      status: "published",
      is_purchasable: true,
      is_sold_out: false,
      total_capacity: 400,
      total_sold: 205,
      ticket_types: [
        createTicket({
          id: "t4-expo-pass",
          event_id: "e2b90c00-2026-4000-8000-000000000002",
          name: "Delegate Pass",
          description: "Access to all keynotes, breakout workshops, and exhibition hall.",
          price_minor: 15000,
          quantity_total: 400,
          quantity_sold: 205,
        }),
      ],
    },
  ];

  public getAll(): DemoEvent[] {
    return this.events;
  }

  public getPublished(): DemoEvent[] {
    return this.events.filter((e) => e.status === "published");
  }

  public getById(id: string): DemoEvent | undefined {
    return this.events.find((e) => e.id === id);
  }

  public getBySlug(slug: string): DemoEvent | undefined {
    return this.events.find((e) => e.slug === slug);
  }

  public create(data: Partial<DemoEvent>): DemoEvent {
    const id = data.id || `evt-${Date.now()}`;
    const newEvent: DemoEvent = {
      id,
      name: data.name || "Untitled Event",
      slug: data.slug || `event-${Date.now()}`,
      description: data.description || "",
      venue_name: data.venue_name || "",
      venue_address: data.venue_address || "",
      banner_url: data.banner_url || null,
      starts_at: data.starts_at || new Date(Date.now() + 86400000 * 14).toISOString(),
      ends_at: data.ends_at || new Date(Date.now() + 86400000 * 14 + 18000000).toISOString(),
      sales_starts_at: data.sales_starts_at || null,
      sales_ends_at: data.sales_ends_at || null,
      timezone: data.timezone || "Africa/Accra",
      currency: "GHS",
      status: "draft",
      is_purchasable: false,
      is_sold_out: false,
      total_capacity: 0,
      total_sold: 0,
      ticket_types: [],
    };

    this.events.unshift(newEvent);
    return newEvent;
  }

  public setStatus(id: string, status: "draft" | "published" | "unpublished" | "cancelled" | "archived"): DemoEvent | undefined {
    const event = this.getById(id);
    if (event) {
      event.status = status;
      event.is_purchasable = status === "published";
    }
    return event;
  }

  public addTicketType(eventId: string, ticketData: Partial<TicketType>): TicketType | undefined {
    const event = this.getById(eventId);
    if (!event) return undefined;

    if (!event.ticket_types) {
      event.ticket_types = [];
    }

    const newTicket = createTicket({
      id: `tt-${Date.now()}`,
      event_id: eventId,
      name: ticketData.name || "General Admission",
      description: ticketData.description ?? "",
      price_minor: ticketData.price_minor || 0,
      quantity_total: ticketData.quantity_total || 100,
      min_per_order: ticketData.min_per_order || 1,
      max_per_order: ticketData.max_per_order || 10,
    });

    event.ticket_types.push(newTicket);
    event.total_capacity = (event.total_capacity || 0) + newTicket.quantity_total;

    return newTicket;
  }

  public setBanner(eventId: string, bannerUrl: string): DemoEvent | undefined {
    const event = this.getById(eventId);
    if (event) {
      event.banner_url = bannerUrl;
    }
    return event;
  }
}

declare global {
  // eslint-disable-next-line no-var
  var __demoEventStore: EventStore | undefined;
}

export const eventStore = global.__demoEventStore || new EventStore();
if (process.env.NODE_ENV !== "production") {
  global.__demoEventStore = eventStore;
}
