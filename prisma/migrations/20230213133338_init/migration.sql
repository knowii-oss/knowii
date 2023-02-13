-- CreateEnum
CREATE TYPE "public"."pricing_plan_interval" AS ENUM ('day', 'week', 'month', 'year');

-- CreateEnum
CREATE TYPE "public"."pricing_type" AS ENUM ('one_time', 'recurring');

-- CreateEnum
CREATE TYPE "public"."subscription_status" AS ENUM ('trialing', 'active', 'canceled', 'incomplete', 'incomplete_expired', 'past_due', 'paused', 'unpaid');

-- CreateEnum
CREATE TYPE "public"."user_role" AS ENUM ('USER', 'ADMIN');

-- CreateTable
CREATE TABLE "public"."clients" (
    "id" UUID NOT NULL,
    "name" TEXT NOT NULL,
    "given_name" TEXT NOT NULL DEFAULT '',
    "family_name" TEXT NOT NULL DEFAULT '',
    "email" TEXT,
    "user_role" "public"."user_role" NOT NULL DEFAULT 'USER',
    "image_url" TEXT,
    "phone" TEXT,
    "user_id" UUID NOT NULL DEFAULT auth.uid(),
    "created_at" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),
    "updated_at" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),

    CONSTRAINT "clients_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."customers" (
    "user_id" UUID NOT NULL,
    "stripe_customer_id" TEXT NOT NULL,

    CONSTRAINT "customers_pkey" PRIMARY KEY ("user_id")
);

-- CreateTable
CREATE TABLE "public"."prices" (
    "id" UUID NOT NULL,
    "product_id" UUID,
    "active" BOOLEAN,
    "description" TEXT,
    "unit_amount" BIGINT,
    "currency" TEXT,
    "type" "public"."pricing_type",
    "interval" "public"."pricing_plan_interval",
    "interval_count" INTEGER,
    "trial_period_days" INTEGER,
    "metadata" JSONB,

    CONSTRAINT "prices_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."products" (
    "id" UUID NOT NULL,
    "active" BOOLEAN,
    "name" TEXT,
    "description" TEXT,
    "image" TEXT,
    "metadata" JSONB,

    CONSTRAINT "products_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."subscriptions" (
    "id" UUID NOT NULL,
    "user_id" UUID NOT NULL,
    "status" "public"."subscription_status",
    "metadata" JSONB,
    "product_id" UUID,
    "price_id" UUID,
    "quantity" INTEGER,
    "cancel_at_period_end" BOOLEAN,
    "created_at" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),
    "current_period_start" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),
    "current_period_end" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),
    "ended_at" TIMESTAMPTZ(6) DEFAULT timezone('utc'::text, now()),
    "cancel_at" TIMESTAMPTZ(6) DEFAULT timezone('utc'::text, now()),
    "canceled_at" TIMESTAMPTZ(6) DEFAULT timezone('utc'::text, now()),
    "trial_start" TIMESTAMPTZ(6) DEFAULT timezone('utc'::text, now()),
    "trial_end" TIMESTAMPTZ(6) DEFAULT timezone('utc'::text, now()),

    CONSTRAINT "subscriptions_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."communities" (
    "id" UUID NOT NULL,
    "name" TEXT NOT NULL DEFAULT '',
    "description" TEXT NOT NULL DEFAULT '',
    "created_at" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),
    "updated_at" TIMESTAMPTZ(6) NOT NULL DEFAULT timezone('utc'::text, now()),

    CONSTRAINT "communities_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."_community_admins" (
    "A" UUID NOT NULL,
    "B" UUID NOT NULL
);

-- CreateTable
CREATE TABLE "public"."_community_members" (
    "A" UUID NOT NULL,
    "B" UUID NOT NULL
);

-- CreateTable
CREATE TABLE "public"."_community_owners" (
    "A" UUID NOT NULL,
    "B" UUID NOT NULL
);

-- CreateIndex
CREATE UNIQUE INDEX "clients_name_key" ON "public"."clients"("name");

-- CreateIndex
CREATE UNIQUE INDEX "communities_name_key" ON "public"."communities"("name");

-- CreateIndex
CREATE INDEX "idx_community_name" ON "public"."communities"("name");

-- CreateIndex
CREATE UNIQUE INDEX "_community_admins_AB_unique" ON "public"."_community_admins"("A", "B");

-- CreateIndex
CREATE INDEX "_community_admins_B_index" ON "public"."_community_admins"("B");

-- CreateIndex
CREATE UNIQUE INDEX "_community_members_AB_unique" ON "public"."_community_members"("A", "B");

-- CreateIndex
CREATE INDEX "_community_members_B_index" ON "public"."_community_members"("B");

-- CreateIndex
CREATE UNIQUE INDEX "_community_owners_AB_unique" ON "public"."_community_owners"("A", "B");

-- CreateIndex
CREATE INDEX "_community_owners_B_index" ON "public"."_community_owners"("B");

-- AddForeignKey
ALTER TABLE "public"."prices" ADD CONSTRAINT "prices_product_id_fkey" FOREIGN KEY ("product_id") REFERENCES "public"."products"("id") ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "public"."subscriptions" ADD CONSTRAINT "subscriptions_price_id_fkey" FOREIGN KEY ("price_id") REFERENCES "public"."prices"("id") ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "public"."subscriptions" ADD CONSTRAINT "subscriptions_product_id_fkey" FOREIGN KEY ("product_id") REFERENCES "public"."products"("id") ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "public"."_community_admins" ADD CONSTRAINT "_community_admins_A_fkey" FOREIGN KEY ("A") REFERENCES "public"."clients"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."_community_admins" ADD CONSTRAINT "_community_admins_B_fkey" FOREIGN KEY ("B") REFERENCES "public"."communities"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."_community_members" ADD CONSTRAINT "_community_members_A_fkey" FOREIGN KEY ("A") REFERENCES "public"."clients"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."_community_members" ADD CONSTRAINT "_community_members_B_fkey" FOREIGN KEY ("B") REFERENCES "public"."communities"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."_community_owners" ADD CONSTRAINT "_community_owners_A_fkey" FOREIGN KEY ("A") REFERENCES "public"."clients"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."_community_owners" ADD CONSTRAINT "_community_owners_B_fkey" FOREIGN KEY ("B") REFERENCES "public"."communities"("id") ON DELETE CASCADE ON UPDATE CASCADE;
