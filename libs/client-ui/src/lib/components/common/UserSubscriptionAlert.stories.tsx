import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { UserSubscriptionAlert } from './UserSubscriptionAlert';

const Story: ComponentMeta<typeof UserSubscriptionAlert> = {
  component: UserSubscriptionAlert,
  title: 'UserSubscriptionAlert',
};
export default Story;

const Template: ComponentStory<typeof UserSubscriptionAlert> = (args) => <UserSubscriptionAlert {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
