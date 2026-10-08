import type { Voting } from "@/types/api"

export type Standing = {
    rank: number
    exhibitor_id: number
    name: string
    photo_url: string | null
    votes: number
}

export type Category = {
    id: number
    name: string
    total_votes: number
    standings: Standing[]
}

export type ResultsSnapshot = {
    event: {
        slug: string
        name: string
    }
    generated_at: string
    total_voters: number
    total_votes: number
    categories: Category[]
    voting: Voting
}
