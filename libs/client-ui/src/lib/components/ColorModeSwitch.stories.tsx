import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { ColorModeSwitch } from './ColorModeSwitch';

const Story: ComponentMeta<typeof ColorModeSwitch> = {
  component: ColorModeSwitch,
  title: 'ColorModeSwitch',
};
export default Story;

const Template: ComponentStory<typeof ColorModeSwitch> = (args) => <ColorModeSwitch {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
