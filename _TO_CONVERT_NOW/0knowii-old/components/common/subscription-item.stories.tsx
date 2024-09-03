import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { SubscriptionItem } from './subscription-item';

const Story: ComponentMeta<typeof SubscriptionItem> = {
  component: SubscriptionItem,
  title: 'SubscriptionItem',
};
export default Story;

const Template: ComponentStory<typeof SubscriptionItem> = (args) => <SubscriptionItem {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
