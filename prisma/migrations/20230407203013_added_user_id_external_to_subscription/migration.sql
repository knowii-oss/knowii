/*
  Warnings:

  - A unique constraint covering the columns `[user_id]` on the table `subscriptions` will be added. If there are existing duplicate values, this will fail.
  - A unique constraint covering the columns `[user_id_external]` on the table `subscriptions` will be added. If there are existing duplicate values, this will fail.

*/
-- AlterTable
ALTER TABLE "public"."subscriptions" ADD COLUMN     "user_id_external" UUID;

-- CreateIndex
CREATE UNIQUE INDEX "subscriptions_user_id_key" ON "public"."subscriptions"("user_id");

-- CreateIndex
CREATE UNIQUE INDEX "subscriptions_user_id_external_key" ON "public"."subscriptions"("user_id_external");
